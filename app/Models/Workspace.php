<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    use SoftDeletes;

    protected $fillable = ['public_id', 'name', 'type', 'owner_user_id'];

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ReferralOffer::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WorkspaceSubscription::class);
    }
}
