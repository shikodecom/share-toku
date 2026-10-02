<?php

namespace App\Services;

use App\Models\ReferralOffer;
use App\Models\ReferralProgram;
use Illuminate\Database\Eloquent\Builder;

class Publishability
{
    public function programAllowed(?ReferralProgram $program, bool $external = false): bool
    {
        if (! $program || $program->trashed()) {
            return false;
        }
        $program->loadMissing('service');
        $policy = $external ? $program->external_distribution_policy : $program->public_listing_policy;

        return $program->is_active && $program->service?->is_active && ! $program->service?->trashed()
            && in_array($policy, ['approved', 'restricted'], true)
            && ($program->starts_at === null || $program->starts_at <= now())
            && ($program->ends_at === null || $program->ends_at >= now());
    }

    public function offerAllowed(ReferralOffer $offer, bool $external = false): bool
    {
        $offer->loadMissing('program.service');

        return $offer->status === 'published' && ! $offer->trashed()
            && ($offer->starts_at === null || $offer->starts_at <= now())
            && ($offer->ends_at === null || $offer->ends_at >= now())
            && $this->programAllowed($offer->program, $external);
    }

    public function offers(bool $external = false): Builder
    {
        return ReferralOffer::query()->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->whereHas('program', fn ($q) => $this->constrainPrograms($q, $external))
            ->with('program.service.categories');
    }

    public function constrainPrograms(Builder $query, bool $external = false): Builder
    {
        $field = $external ? 'external_distribution_policy' : 'public_listing_policy';

        return $query->where('is_active', true)->whereIn($field, ['approved', 'restricted'])
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->whereHas('service', fn ($q) => $q->where('is_active', true));
    }
}
