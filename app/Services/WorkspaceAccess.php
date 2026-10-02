<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;

class WorkspaceAccess
{
    public function current(User $user, ?string $publicId = null): Workspace
    {
        $query = Workspace::query()->whereHas('members', fn ($q) => $q->where('user_id', $user->id));
        if ($publicId) {
            $query->where('public_id', $publicId);
        }
        $workspace = $query->orderBy('id')->first();
        if (! $workspace) {
            throw new AuthorizationException('Workspace access denied.');
        }

        return $workspace;
    }

    public function requireRole(User $user, Workspace $workspace, array $roles): void
    {
        $role = $workspace->members()->where('user_id', $user->id)->value('role');
        if (! in_array($role, $roles, true)) {
            throw new AuthorizationException('Role not permitted.');
        }
    }

    public function requireSystemAdmin(User $user): void
    {
        if (! $user->is_system_admin) {
            throw new AuthorizationException('System administrator required.');
        }
    }
}
