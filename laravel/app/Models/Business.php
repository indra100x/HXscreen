<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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
        return $this->hasMany(Screen::class);
    }
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }
}
