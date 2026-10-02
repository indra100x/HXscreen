<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Screen extends Model
{
    use HasUuids;

    protected $table = 'screen';

    protected $fillable = [
        'busniss_id',
        'name',
        'device_id',
        'pairing_code',
        'device_token',
        'pairing_code_expires_at',
        'device_token_expires_at',
        'paired_at',
        'last_seen_at',
    ];

    protected $casts = [
        'pairing_code_expires_at' => 'datetime',
        'device_token_expires_at' => 'datetime',
        'paired_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'busniss_id');
    }

    public function screenPlaylists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'screen_playlist', 'screen_id', 'playlist_id');
    }
}
