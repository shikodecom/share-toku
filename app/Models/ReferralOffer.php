<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralOffer extends Model
{
    use SoftDeletes;

    protected $fillable = ['public_id', 'workspace_id', 'referral_program_id', 'title', 'referral_code', 'referral_url', 'invitee_benefit_override', 'inviter_benefit_override', 'conditions_override', 'starts_at', 'ends_at', 'last_verified_at', 'status', 'created_by_user_id', 'updated_by_user_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'last_verified_at' => 'datetime'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ReferralProgram::class, 'referral_program_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
