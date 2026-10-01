<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Screen extends Model
{
    protected $table = 'screen';
    protected $fillable = ['busniss_id', 'name', 'device_id'];
    public function business()
    {
        return $this->belongsTo(Business::class, 'busniss_id');
    }
    public function screenPlaylists()
    {
        return $this->belongsToMany(Playlist::class, 'screen_playlist', 'screen_id', 'playlist_id');
    }


}
