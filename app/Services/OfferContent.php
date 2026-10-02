<?php

namespace App\Services;

use App\Models\ReferralOffer;

class OfferContent
{
    public function forDistribution(ReferralOffer $offer): array
    {
        $offer->loadMissing('program.service');
        $p = $offer->program;

        return [
            'public_id' => $offer->public_id, 'service' => ['name' => $p->service->name, 'slug' => $p->service->slug],
            'program' => ['name' => $p->name],
            'referral_code' => $offer->referral_code, 'referral_url' => $offer->referral_url,
            'invitee_benefit' => filled($offer->invitee_benefit_override) ? $offer->invitee_benefit_override : $p->invitee_benefit_text,
            'conditions' => filled($offer->conditions_override) ? $offer->conditions_override : $p->conditions_text,
            'ends_at' => $offer->ends_at?->toIso8601String(), 'last_verified_at' => $offer->last_verified_at?->toIso8601String(),
        ];
    }
}
