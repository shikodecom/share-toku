<?php

namespace App\Services;

use App\Models\ReferralOffer;
use App\Models\Site;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class OperatorMatcher
{
    public function __construct(private Publishability $publishability, private EntitlementService $entitlements) {}

    public function match(Site $site, ReferralOffer $owner): ?ReferralOffer
    {
        if (! config('sharetoku.operator_offers_enabled')) {
            return null;
        }
        if (! $this->entitlements->get($site->workspace, 'operator_offer_enabled')
            || ! $site->consents()->where('terms_version', config('sharetoku.consent_version'))->whereNull('withdrawn_at')->exists()) {
            return null;
        }
        $publicId = config('sharetoku.operator_workspace_public_id');
        $operator = $publicId ? Workspace::where('public_id', $publicId)->first() : null;
        if (! $operator || $operator->id === $site->workspace_id) {
            return null;
        }
        $owner->loadMissing('program.service.categories');
        $ownerCategoryIds = $owner->program->service->categories->pluck('id')->all();
        if (! $ownerCategoryIds) {
            return null;
        }
        $excluded = DB::table('site_category_exclusions')->where('site_id', $site->id)->pluck('category_id')->all();
        $relations = DB::table('category_relations')->where('is_active', true)->where('relevance_score', '>', 0)->get();
        $ranked = [];
        foreach ($this->publishability->offers(true)->where('workspace_id', $operator->id)->get() as $candidate) {
            if ($candidate->program->service_id === $owner->program->service_id) {
                continue;
            }
            $candidateIds = $candidate->program->service->categories->pluck('id')->all();
            if (array_intersect($candidateIds, $excluded)) {
                continue;
            }
            $score = array_intersect($ownerCategoryIds, $candidateIds) ? 100 : 0;
            foreach ($relations as $relation) {
                if ((in_array($relation->source_category_id, $ownerCategoryIds) && in_array($relation->target_category_id, $candidateIds))
                    || ($relation->is_bidirectional && in_array($relation->target_category_id, $ownerCategoryIds) && in_array($relation->source_category_id, $candidateIds))) {
                    $score = max($score, $relation->relevance_score);
                }
            }
            if ($score > 0) {
                $ranked[] = ['offer' => $candidate, 'score' => $score,
                    'rotation' => hash('sha256', $site->public_id.$owner->public_id.now()->toDateString().$candidate->public_id)];
            }
        }
        usort($ranked, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['rotation'], $b['rotation']) ?: strcmp($a['offer']->public_id, $b['offer']->public_id));

        return $ranked[0]['offer'] ?? null;
    }
}
