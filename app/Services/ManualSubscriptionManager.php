<?php

namespace App\Services;

use App\Contracts\SubscriptionManager;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use Illuminate\Support\Facades\DB;

class ManualSubscriptionManager implements SubscriptionManager
{
    public function __construct(private Audit $audit) {}

    public function change(Workspace $workspace, array $subscription, int $actorUserId): WorkspaceSubscription
    {
        return DB::transaction(function () use ($workspace, $subscription, $actorUserId) {
            Workspace::whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $workspace->subscriptions()->whereIn('status', ['active', 'trialing'])->update(['status' => 'cancelled', 'ends_at' => now()]);
            $row = WorkspaceSubscription::create($subscription + ['workspace_id' => $workspace->id]);
            $this->audit->record($actorUserId, $workspace->id, $row, 'subscription_change', null, $subscription);

            return $row;
        });
    }
}
