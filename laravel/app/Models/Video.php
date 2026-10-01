<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
   protected $table = 'video';
   protected $fillable = ['business_id', 'name', 'url'];
   public function business()
   {
       return $this->belongsTo(Business::class);
   }
   public function playlists()
   {
       return $this->belongsToMany(Playlist::class, 'playlist_video', 'video_id', 'playlist_id');
   }
}
