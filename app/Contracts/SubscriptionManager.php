<?php

namespace App\Contracts;

use App\Models\Workspace;
use App\Models\WorkspaceSubscription;

interface SubscriptionManager
{
    public function change(Workspace $workspace, array $subscription, int $actorUserId): WorkspaceSubscription;
}
