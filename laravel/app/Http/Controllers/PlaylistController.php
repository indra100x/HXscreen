<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        if ($playlist->business->user_id !== $user->id) {
            abort(403);
        }

        return response()->json(['playlist' => $playlist]);
    }

    public function destroy(Playlist $playlist)
    {
        $user = auth()->user();

        Log::info('PlaylistController@destroy', [
            'user_id' => $user?->id,
            'playlist_id' => $playlist->id,
        ]);

        if ($playlist->business->user_id !== $user->id) {
            abort(403);
        }

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

        if ($playlist->business->user_id !== $user->id) {
            abort(403);
        }

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

        if ($playlist->business->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'video_id' => 'required|exists:video,id',
        ]);

        $playlist->videos()->attach($request->video_id);

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

        if ($playlist->business->user_id !== $user->id) {
            abort(403);
        }

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

        if ($playlist->business->user_id !== $user->id) {
            abort(403);
        }

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

        if ($playlist->business->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'exists:video,id',
        ]);

        $playlist->videos()->sync($request->video_ids);

        return response()->json([
            'message' => 'Videos ordered successfully',
        ], 200);
    }
}
