<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAuthorizationCode extends Model
{
    public $timestamps = false;

    protected $fillable = ['public_id', 'site_id', 'user_id', 'code_hash', 'code_challenge', 'redirect_uri', 'state_hash', 'expires_at', 'consumed_at', 'created_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
