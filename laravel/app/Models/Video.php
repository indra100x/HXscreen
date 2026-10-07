<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class Video extends Model
{
    use HasUuids;

    protected $table = 'video';

    protected $fillable = ['busniss_id', 'disk', 'name', 'url', 'size'];

    protected $attributes = ['disk' => 'r2'];

    protected $appends = ['media_url'];

    /**
     * Time-boxed playable URL. R2 rows get a presigned link straight from
     * Cloudflare; legacy local rows keep the Laravel-signed media route.
     * Raw storage paths are never exposed.
     */
    protected function mediaUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->signedMediaUrl());
    }

    protected function signedMediaUrl(): string
    {
        if (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://')) {
            return $this->url;
        }

        if (($this->disk) === 'r2') {
            return Storage::disk('r2')->temporaryUrl($this->url, now()->addHours(6));
        }

        return URL::signedRoute('media.show', ['video' => $this->id], now()->addHours(6));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'busniss_id');
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_video', 'video_id', 'playlist_id');
    }
}
