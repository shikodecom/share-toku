<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteConsent extends Model
{
    protected $fillable = ['site_id', 'user_id', 'terms_version', 'consented_at', 'withdrawn_at'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }
}
