<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Video extends Model
{
    protected $table = 'video';
    protected $fillable = ['busniss_id', 'name', 'url'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'busniss_id');
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_video', 'video_id', 'playlist_id');
    }
}
