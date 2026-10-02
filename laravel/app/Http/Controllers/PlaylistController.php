<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Playlist;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlaylistController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        Log::info('PlaylistController@index', [
            'user_id' => $user?->id,
            'route' => $request->route()?->getName(),
        ]);

        $businessIds = $user->businesses()->pluck('id')->toArray();

        $playlists = Playlist::whereIn('busniss_id', $businessIds)->get();

        return response()->json($playlists);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        Log::info('PlaylistController@store', [
            'user_id' => $user?->id,
            'payload' => $request->only(['name', 'busniss_id']),
        ]);

        $request->validate([
            'name' => 'required|string|max:50',
            'busniss_id' => 'required|exists:busniss,id',
        ]);

        $this->ensureBusinessOwned($user, $request->busniss_id);

        $playlist = Playlist::create([
            'name' => $request->name,
            'busniss_id' => $request->busniss_id,
        ]);

        return response()->json([
            'message' => 'Playlist created successfully',
            'playlist' => $playlist,
        ], 201);
    }

    public function show(Playlist $playlist)
    {
        $user = auth()->user();

        Log::info('PlaylistController@show', [
            'user_id' => $user?->id,
            'playlist_id' => $playlist->id,
        ]);

        $this->authorizePlaylist($user, $playlist);

        return response()->json(['playlist' => $playlist]);
    }

    public function destroy(Playlist $playlist)
    {
        $user = auth()->user();

        Log::info('PlaylistController@destroy', [
            'user_id' => $user?->id,
            'playlist_id' => $playlist->id,
        ]);

        $this->authorizePlaylist($user, $playlist);

        $playlist->delete();

        return response()->json(['message' => 'Playlist deleted successfully'], 200);
    }

    public function update(Request $request, Playlist $playlist)
    {
        $user = auth()->user();

        Log::info('PlaylistController@update', [
            'user_id' => $user?->id,
            'playlist_id' => $playlist->id,
            'payload' => $request->only(['name']),
        ]);

        $this->authorizePlaylist($user, $playlist);

        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $playlist->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Playlist updated successfully',
            'playlist' => $playlist,
        ], 200);
    }

    public function addVideo(Request $request, Playlist $playlist)
    {
        $user = auth()->user();

        Log::info('PlaylistController@addVideo', [
            'user_id' => $user?->id,
            'playlist_id' => $playlist->id,
            'payload' => $request->only(['video_id']),
        ]);

        $this->authorizePlaylist($user, $playlist);

        $request->validate([
            'video_id' => 'required|exists:video,id',
        ]);

        $video = Video::findOrFail($request->video_id);

        if (! $video->business || $video->business->user_id !== $user->id) {
            abort(403);
        }

        if ($playlist->videos()->where('video.id', $video->id)->exists()) {
            return response()->json(['message' => 'Video already in playlist'], 409);
        }

        $maxOrder = $playlist->videos()->max('playlist_video.order') ?? -1;

        $playlist->videos()->attach($video->id, [
            'id' => (string) Str::uuid(),
            'order' => $maxOrder + 1,
        ]);

        return response()->json([
            'message' => 'Video added to playlist successfully',
        ], 200);
    }

    public function removeVideo(Request $request, Playlist $playlist)
    {
        $user = auth()->user();

        Log::info('PlaylistController@removeVideo', [
            'user_id' => $user?->id,
            'playlist_id' => $playlist->id,
            'payload' => $request->only(['video_id']),
        ]);

        $this->authorizePlaylist($user, $playlist);

        $request->validate([
            'video_id' => 'required|exists:video,id',
        ]);

        $playlist->videos()->detach($request->video_id);

        return response()->json([
            'message' => 'Video removed from playlist successfully',
        ], 200);
    }

    public function getVideos(Playlist $playlist)
    {
        $user = auth()->user();

        Log::info('PlaylistController@getVideos', [
            'user_id' => $user?->id,
            'playlist_id' => $playlist->id,
        ]);

        $this->authorizePlaylist($user, $playlist);

        return response()->json($playlist->videos);
    }

    public function orderVideos(Request $request, Playlist $playlist)
    {
        $user = auth()->user();

        Log::info('PlaylistController@orderVideos', [
            'user_id' => $user?->id,
            'playlist_id' => $playlist->id,
            'payload' => $request->only(['video_ids']),
        ]);

        $this->authorizePlaylist($user, $playlist);

        $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'exists:video,id',
        ]);

        $ownedIds = Video::whereIn('id', $request->video_ids)
            ->whereHas('business', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id')
            ->all();

        if (count($ownedIds) !== count(array_unique($request->video_ids))) {
            abort(403);
        }

        $existingOrders = $playlist->videos()
            ->whereIn('video.id', $request->video_ids)
            ->get()
            ->keyBy('id');

        $sync = [];

        foreach ($request->video_ids as $index => $videoId) {
            $sync[$videoId] = [
                'id' => $existingOrders->get($videoId)?->pivot->id ?? (string) Str::uuid(),
                'order' => $index,
            ];
        }

        $playlist->videos()->sync($sync);

        return response()->json([
            'message' => 'Videos ordered successfully',
        ], 200);
    }

    protected function authorizePlaylist($user, Playlist $playlist): void
    {
        if (! $playlist->business) {
            abort(404);
        }

        if ($playlist->business->user_id !== $user->id) {
            abort(403);
        }
    }

    protected function ensureBusinessOwned($user, string $businessId): void
    {
        $business = Business::findOrFail($businessId);

        if ($business->user_id !== $user->id) {
            abort(403);
        }
    }
}
