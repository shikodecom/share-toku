<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewRequest extends Model
{
    protected $fillable = ['public_id', 'referral_offer_id', 'submitted_by_user_id', 'submitted_at', 'status', 'reviewed_by_user_id', 'reviewed_at', 'decision_reason', 'snapshot_json'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'snapshot_json' => 'array'];
    }
}
