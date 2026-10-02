<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceSubscription extends Model
{
    protected $fillable = ['workspace_id', 'plan_code', 'status', 'starts_at', 'ends_at', 'billing_provider', 'provider_subscription_id', 'metadata_json'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'metadata_json' => 'array'];
    }
}
