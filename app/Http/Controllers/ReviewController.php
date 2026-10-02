<?php

namespace App\Http\Controllers;

use App\Models\ReferralOffer;
use App\Models\ReviewRequest;
use App\Services\Audit;
use App\Services\Publishability;
use App\Services\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function submit(Request $request, string $workspace, string $offer, WorkspaceAccess $access, Audit $audit)
    {
        $w = $access->current($request->user(), $workspace);
        $access->requireRole($request->user(), $w, ['member', 'editor', 'administrator']);
        $o = $w->offers()->with('program')->where('public_id', $offer)->firstOrFail();
        if (! in_array($o->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid transition.']);
        }

        return DB::transaction(function () use ($o, $request, $w, $audit) {
            $snapshot = ['offer' => $o->only(['public_id', 'referral_program_id', 'referral_code', 'referral_url', 'starts_at', 'ends_at', 'invitee_benefit_override', 'conditions_override']),
                'program' => $o->program->only(['public_listing_policy', 'external_distribution_policy', 'policy_notes', 'policy_checked_at'])];
            $r = ReviewRequest::create(['public_id' => (string) Str::ulid(), 'referral_offer_id' => $o->id, 'submitted_by_user_id' => $request->user()->id,
                'submitted_at' => now(), 'status' => 'pending', 'snapshot_json' => $snapshot]);
            $o->update(['status' => 'pending_review']);
            $audit->record($request->user()->id, $w->id, $o, 'submit', ['status' => 'draft'], ['status' => 'pending_review']);

            return response()->json($r, 201);
        });
    }

    public function pending(Request $request, WorkspaceAccess $access)
    {
        $this->reviewer($request, $access);
        $query = ReviewRequest::where('status', 'pending');
        if (! $request->user()->is_system_admin) {
            $workspaceIds = $request->user()->workspaces()->wherePivot('role', 'reviewer')->pluck('workspaces.id');
            $query->whereIn('referral_offer_id', ReferralOffer::whereIn('workspace_id', $workspaceIds)->select('id'));
        }

        return response()->json($query->latest()->paginate(30));
    }

    public function decide(Request $request, string $review, WorkspaceAccess $access, Publishability $publishability, Audit $audit)
    {
        $this->reviewer($request, $access);
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'reason' => 'required_if:decision,reject|nullable|string|max:2000']);

        return DB::transaction(function () use ($request, $review, $data, $publishability, $audit) {
            $r = ReviewRequest::where('public_id', $review)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Review is closed.']);
            }
            if ($r->submitted_by_user_id === $request->user()->id) {
                throw ValidationException::withMessages(['review' => 'Self approval is forbidden.']);
            }
            $o = ReferralOffer::with('program.service')->lockForUpdate()->findOrFail($r->referral_offer_id);
            if (! $request->user()->is_system_admin && ! $request->user()->workspaces()->where('workspaces.id', $o->workspace_id)->wherePivot('role', 'reviewer')->exists()) {
                abort(403);
            }
            if ($o->status !== 'pending_review') {
                throw ValidationException::withMessages(['status' => 'Offer is not pending.']);
            }
            if ($data['decision'] === 'approve' && ! $publishability->programAllowed($o->program)) {
                throw ValidationException::withMessages(['program' => 'Program cannot be published.']);
            }
            $status = $data['decision'] === 'approve' ? 'published' : 'rejected';
            $r->update(['status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(), 'decision_reason' => $data['reason'] ?? null]);
            $o->update(['status' => $status]);
            $audit->record($request->user()->id, $o->workspace_id, $o, $data['decision'], ['status' => 'pending_review'], ['status' => $status]);

            return response()->json($r);
        });
    }

    public function suspend(Request $request, string $offer, WorkspaceAccess $access, Audit $audit)
    {
        $access->requireSystemAdmin($request->user());
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $o = ReferralOffer::where('public_id', $offer)->firstOrFail();
        if ($o->status !== 'published') {
            throw ValidationException::withMessages(['status' => 'Only published offers can be suspended.']);
        }
        $o->update(['status' => 'suspended']);
        $audit->record($request->user()->id, $o->workspace_id, $o, 'suspend', ['status' => 'published'], ['status' => 'suspended', 'reason' => $data['reason']]);

        return response()->json(['status' => 'suspended']);
    }

    public function reopen(Request $request, string $offer, WorkspaceAccess $access, Audit $audit)
    {
        $access->requireSystemAdmin($request->user());
        $o = ReferralOffer::where('public_id', $offer)->firstOrFail();
        if ($o->status !== 'suspended') {
            throw ValidationException::withMessages(['status' => 'Only suspended offers can be reopened.']);
        }
        $o->update(['status' => 'draft']);
        $audit->record($request->user()->id, $o->workspace_id, $o, 'reopen', ['status' => 'suspended'], ['status' => 'draft']);

        return response()->json(['status' => 'draft']);
    }

    private function reviewer(Request $request, WorkspaceAccess $access): void
    {
        if ($request->user()->is_system_admin || $request->user()->workspaces()->wherePivot('role', 'reviewer')->exists()) {
            return;
        }
        $access->requireSystemAdmin($request->user());
    }
}
