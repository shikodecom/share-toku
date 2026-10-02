<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_user_id', 'workspace_id', 'auditable_type', 'auditable_id', 'action', 'before_json', 'after_json', 'metadata_json', 'created_at'];

    protected function casts(): array
    {
        return ['before_json' => 'array', 'after_json' => 'array', 'metadata_json' => 'array', 'created_at' => 'datetime'];
    }
}
