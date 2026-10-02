<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public function record(?int $actor, ?int $workspace, Model $subject, string $action, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create(['actor_user_id' => $actor, 'workspace_id' => $workspace, 'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(), 'action' => $action, 'before_json' => $before, 'after_json' => $after, 'created_at' => now()]);
    }
}
