<?php

namespace App\Http\Controllers;

use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use App\Services\EntitlementService;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OfferController extends Controller
{
    public function index(Request $request, string $workspace, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);

        return response()->json($w->offers()->with('program.service')->latest()->paginate(30));
    }

    public function show(Request $request, string $workspace, string $offer, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);

        return response()->json($w->offers()->with('program.service')->where('public_id', $offer)->firstOrFail());
    }

    public function store(Request $request, string $workspace, WorkspaceAccess $access, EntitlementService $entitlements)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['member', 'editor', 'administrator']);
        if (! $entitlements->canCreateOffer($w)) {
            throw ValidationException::withMessages(['limit' => 'Offer limit reached.']);
        }
        $data = $this->validated($request);
        $this->activeProgram($data['referral_program_id']);
        $data += ['public_id' => (string) Str::ulid(), 'workspace_id' => $w->id, 'created_by_user_id' => $request->user()->id, 'status' => 'draft'];

        return response()->json(ReferralOffer::create($data), 201);
    }

    public function update(Request $request, string $workspace, string $offer, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['member', 'editor', 'administrator']);
        $record = $w->offers()->where('public_id', $offer)->firstOrFail();
        if (! in_array($record->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Offer cannot be edited in this state.']);
        }
        $data = $this->validated($request);
        $this->activeProgram($data['referral_program_id']);
        $record->update($data + ['status' => 'draft', 'updated_by_user_id' => $request->user()->id]);

        return response()->json($record);
    }

    public function destroy(Request $request, string $workspace, string $offer, WorkspaceAccess $access)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['member', 'editor', 'administrator']);
        $w->offers()->where('public_id', $offer)->firstOrFail()->delete();

        return response()->noContent();
    }

    private function activeProgram(int $id): void
    {
        $p = ReferralProgram::with('service')->find($id);
        if (! $p || ! $p->is_active || ! $p->service?->is_active) {
            throw ValidationException::withMessages(['referral_program_id' => 'Program is inactive.']);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['referral_program_id' => 'required|integer|exists:referral_programs,id', 'title' => 'nullable|string|max:255',
            'referral_code' => 'nullable|string|max:255', 'referral_url' => 'nullable|string|max:2048',
            'invitee_benefit_override' => 'nullable|string', 'inviter_benefit_override' => 'nullable|string', 'conditions_override' => 'nullable|string',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at', 'last_verified_at' => 'nullable|date']);
        if (blank($data['referral_code'] ?? null) && blank($data['referral_url'] ?? null)) {
            throw ValidationException::withMessages(['referral_code' => 'Code or URL is required.']);
        }
        if (! blank($data['referral_url'] ?? null)) {
            $url = $data['referral_url'];
            $parts = parse_url($url);
            if (preg_match('/[\x00-\x1F\x7F]/', $url) || ! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages(['referral_url' => 'Invalid referral URL.']);
            }
        }

        return $data;
    }
}
