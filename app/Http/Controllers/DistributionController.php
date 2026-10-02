<?php

namespace App\Http\Controllers;

use App\Models\Placement;
use App\Services\EntitlementService;
use App\Services\EventToken;
use App\Services\OfferContent;
use App\Services\OperatorMatcher;
use App\Services\Publishability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DistributionController extends Controller
{
    public function me(Request $request, EntitlementService $entitlements)
    {
        $site = $request->attributes->get('site');

        return response()->json(['public_id' => $site->public_id, 'domain' => $site->domain, 'status' => $site->status,
            'plan' => $entitlements->plan($site->workspace), 'consent' => $this->consented($site),
            'features' => ['operator_offer_enabled' => (bool) $entitlements->get($site->workspace, 'operator_offer_enabled')]]);
    }

    public function offers(Request $request, Publishability $publishability, OfferContent $content)
    {
        $site = $request->attributes->get('site');
        $data = $request->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $query = $publishability->offers(true)->where('workspace_id', $site->workspace_id);
        if ($q = $data['q'] ?? null) {
            $query->whereHas('program', fn ($p) => $p->where('name', 'like', '%'.$q.'%')->orWhereHas('service', fn ($s) => $s->where('name', 'like', '%'.$q.'%')));
        }

        return response()->json($query->orderBy('public_id')->paginate(20)->through(fn ($o) => $content->forDistribution($o)));
    }

    public function resolve(Request $request, Publishability $publishability, EntitlementService $entitlements, OperatorMatcher $matcher, OfferContent $content, EventToken $eventToken)
    {
        $data = $request->validate(['owner_offer_id' => 'required|string|size:26', 'placement_key' => 'required|string|max:150|regex:/^[A-Za-z0-9._:-]+$/', 'page_url' => 'nullable|url|max:2048']);
        $site = $request->attributes->get('site');
        $owner = $publishability->offers(true)->where('workspace_id', $site->workspace_id)->where('public_id', $data['owner_offer_id'])->first();
        if (! $owner) {
            return response()->json(['error' => ['code' => 'offer_not_available', 'message' => 'Offer not available.']], 404);
        }
        $operator = $entitlements->get($site->workspace, 'operator_offer_enabled') && $this->consented($site) ? $matcher->match($site, $owner) : null;
        $placement = Placement::firstOrCreate(['site_id' => $site->id, 'owner_referral_offer_id' => $owner->id, 'placement_key' => $data['placement_key']],
            ['public_id' => (string) Str::ulid()]);
        $placement->update(['last_resolved_at' => now()]);
        $seconds = max(0, (int) config('sharetoku.cache_ttl'));
        foreach ([$owner->ends_at, $owner->program->ends_at] as $end) {
            if ($end) {
                $seconds = min($seconds, max(0, (int) now()->diffInSeconds($end, false)));
            }
        }
        if ($seconds === 0) {
            return response()->json(['error' => ['code' => 'offer_not_available', 'message' => 'Offer not available.']], 404);
        }
        if ($operator) {
            $operatorSeconds = $seconds;
            foreach ([$operator->ends_at, $operator->program->ends_at] as $end) {
                if ($end) {
                    $operatorSeconds = min($operatorSeconds, max(0, (int) now()->diffInSeconds($end, false)));
                }
            }
            if ($operatorSeconds === 0) {
                $operator = null;
            } else {
                $seconds = $operatorSeconds;
            }
        }
        $issued = $eventToken->issue(['site' => $site->public_id, 'placement' => $placement->public_id,
            'owner' => $owner->public_id, 'operator' => $operator?->public_id]);
        $result = ['schema_version' => 1, 'plan' => $entitlements->plan($site->workspace), 'placement_id' => $placement->public_id,
            'owner_offer' => $content->forDistribution($owner), 'operator_offer' => $operator ? $content->forDistribution($operator) + ['disclosure_label' => 'PR・ShareToku運営者の紹介特典'] : null,
            'cache' => ['ttl_seconds' => $seconds, 'resolved_at' => now()->toIso8601String(), 'expires_at' => now()->addSeconds($seconds)->toIso8601String()],
            'analytics' => ['event_token' => $issued['token'], 'event_token_expires_at' => $issued['expires_at']]];

        Log::info('placement_resolve', ['request_id' => (string) Str::ulid(), 'site_public_id' => $site->public_id,
            'placement_public_id' => $placement->public_id, 'outcome' => $operator ? 'owner_plus_operator' : 'owner_only']);

        return response()->json($result)->header('Cache-Control', 'private, max-age='.$seconds);
    }

    private function consented($site): bool
    {
        return $site->consents()->where('terms_version', config('sharetoku.consent_version'))->whereNull('withdrawn_at')->exists();
    }
}
