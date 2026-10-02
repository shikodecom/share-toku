<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Models\ReferralOffer;
use App\Models\Site;
use App\Services\EventToken;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function batch(Request $request, EventToken $tokens)
    {
        if (strlen($request->getContent()) > 65536) {
            return response()->json(['error' => ['code' => 'payload_too_large', 'message' => 'Payload too large.']], 413);
        }
        $data = $request->validate(['event_token' => 'required|string|max:4096', 'events' => 'required|array|min:1|max:20',
            'events.*.event_id' => 'required|string|size:26', 'events.*.type' => 'required|in:impression,click',
            'events.*.slot' => 'required|in:owner,operator', 'events.*.occurred_at' => 'required|date',
            'events.*.content_key' => 'nullable|string|max:150|regex:/^[A-Za-z0-9._:-]+$/', 'events.*.page_path' => 'nullable|string|max:2048']);
        $claims = $tokens->verify($data['event_token']);
        if (! $claims) {
            return response()->json(['error' => ['code' => 'invalid_event_token', 'message' => 'Event token invalid.']], 401);
        }
        $site = Site::where('public_id', $claims['site'] ?? '')->where('status', 'active')->first();
        $origin = $request->header('Origin');
        if ($origin && strtolower(parse_url($origin, PHP_URL_HOST) ?? '') !== $site?->domain) {
            return response()->json(['error' => ['code' => 'invalid_origin', 'message' => 'Origin is not registered.']], 403);
        }
        $placement = Placement::where('public_id', $claims['placement'] ?? '')->where('site_id', $site?->id)->first();
        if (! $site || ! $placement) {
            return response()->json(['error' => ['code' => 'invalid_event_token', 'message' => 'Event token invalid.']], 401);
        }
        $offerIds = ReferralOffer::whereIn('public_id', array_filter([$claims['owner'] ?? null, $claims['operator'] ?? null]))->pluck('id', 'public_id');
        $rows = [];
        foreach ($data['events'] as $event) {
            if (! Str::isUlid($event['event_id'])) {
                throw ValidationException::withMessages(['event_id' => 'Invalid event ID.']);
            }
            $publicId = $claims[$event['slot']] ?? null;
            if (! $publicId || ! isset($offerIds[$publicId])) {
                throw ValidationException::withMessages(['slot' => 'Slot unavailable.']);
            }
            $at = now()->parse($event['occurred_at']);
            if ($at->gt(now()->addMinutes(5)) || $at->lt(now()->subDays(2))) {
                throw ValidationException::withMessages(['occurred_at' => 'Timestamp out of range.']);
            }
            $path = $event['page_path'] ?? null;
            if ($path !== null) {
                $path = explode('#', explode('?', $path, 2)[0], 2)[0];
                $path = substr(preg_replace('/[\x00-\x1F\x7F]/', '', $path), 0, 512);
                if (! str_starts_with($path, '/')) {
                    $path = null;
                }
            }
            $rows[] = ['event_id' => $event['event_id'], 'occurred_at' => $at, 'received_at' => now(), 'event_type' => $event['type'],
                'site_id' => $site->id, 'placement_id' => $placement->id, 'referral_offer_id' => $offerIds[$publicId], 'slot' => $event['slot'],
                'content_key' => $event['content_key'] ?? null, 'page_path' => $path, 'created_at' => now()];
        }
        $inserted = DB::table('analytics_events')->insertOrIgnore($rows);

        return response()->json(['accepted' => $inserted]);
    }

    public function summary(Request $request, string $workspace, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'site' => 'nullable|string|size:26']);
        $query = DB::table('daily_metrics')->join('sites', 'sites.id', '=', 'daily_metrics.site_id')
            ->join('referral_offers', 'referral_offers.id', '=', 'daily_metrics.referral_offer_id')
            ->where('sites.workspace_id', $w->id)->select('metric_date', 'sites.public_id as site', 'referral_offers.public_id as offer', 'slot')
            ->selectRaw('SUM(impressions) as impressions, SUM(clicks) as clicks')
            ->groupBy('metric_date', 'sites.public_id', 'referral_offers.public_id', 'slot');
        if (isset($data['from'])) {
            $query->whereDate('metric_date', '>=', $data['from']);
        }
        if (isset($data['to'])) {
            $query->whereDate('metric_date', '<=', $data['to']);
        }
        if (isset($data['site'])) {
            $query->where('sites.public_id', $data['site']);
        }
        $rows = $query->orderByDesc('metric_date')->get()->map(function ($row) {
            $row->ctr = $row->impressions ? $row->clicks / $row->impressions : 0;

            return $row;
        });

        return response()->json($rows);
    }
}
