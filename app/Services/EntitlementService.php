<?php

namespace App\Services;

use App\Models\Workspace;

class EntitlementService
{
    public function plan(Workspace $workspace): string
    {
        $subscription = $workspace->subscriptions()->whereIn('status', ['active', 'trialing'])
            ->where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('starts_at')->first();

        return $subscription?->plan_code ?? 'free';
    }

    public function get(Workspace $workspace, string $feature): mixed
    {
        return config('sharetoku.plans.'.$this->plan($workspace).'.'.$feature);
    }

    public function canCreateSite(Workspace $workspace): bool
    {
        return $workspace->sites()->count() < $this->get($workspace, 'max_sites');
    }

    public function canCreateOffer(Workspace $workspace): bool
    {
        return $workspace->offers()->count() < $this->get($workspace, 'max_offers');
    }

    public function usage(Workspace $workspace): array
    {
        return ['plan' => $this->plan($workspace), 'sites' => $workspace->sites()->count(), 'offers' => $workspace->offers()->count(),
            'max_sites' => $this->get($workspace, 'max_sites'), 'max_offers' => $this->get($workspace, 'max_offers')];
    }
}
