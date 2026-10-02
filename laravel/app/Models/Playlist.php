<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Playlist extends Model
{
    use HasUuids;

    protected $table = 'playlist';
    protected $fillable = ['name', 'busniss_id'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'busniss_id');
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'playlist_video', 'playlist_id', 'video_id');
    }
}
