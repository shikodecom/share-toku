<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Placement extends Model
{
    protected $fillable = ['public_id', 'site_id', 'owner_referral_offer_id', 'placement_key', 'last_resolved_at'];

    protected function casts(): array
    {
        return ['last_resolved_at' => 'datetime'];
    }
}
