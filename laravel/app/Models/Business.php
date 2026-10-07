<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasUuids;

    protected $table = 'busniss';

    protected $fillable = ['user_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class, 'busniss_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'busniss_id');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class, 'busniss_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user', 'busniss_id', 'user_id')
            ->withTimestamps();
    }

    public function isOwnedBy(?object $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function isAccessibleBy(?object $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isOwnedBy($user)) {
            return true;
        }

        return $this->members()->where('users.id', $user->id)->exists();
    }
}
