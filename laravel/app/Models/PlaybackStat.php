<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybackStat extends Model
{
    use HasUuids;

    protected $table = 'playback_stats';

    protected $fillable = ['screen_id', 'busniss_id', 'video_id', 'date', 'seconds'];

    protected $casts = [
        'seconds' => 'integer',
    ];

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class, 'screen_id');
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
