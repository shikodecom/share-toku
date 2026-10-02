<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAccessToken extends Model
{
    public $timestamps = false;

    protected $fillable = ['public_id', 'site_id', 'token_hash', 'scopes_json', 'last_used_at', 'expires_at', 'revoked_at', 'created_by_user_id', 'created_at'];

    protected function casts(): array
    {
        return ['scopes_json' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
