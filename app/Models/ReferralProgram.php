<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralProgram extends Model
{
    use SoftDeletes;

    protected $fillable = ['public_id', 'service_id', 'name', 'description', 'invitee_benefit_text', 'inviter_benefit_text', 'conditions_text', 'starts_at', 'ends_at', 'official_terms_url', 'public_listing_policy', 'external_distribution_policy', 'policy_notes', 'policy_checked_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'policy_checked_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
