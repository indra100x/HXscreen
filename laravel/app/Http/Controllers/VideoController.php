<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        Log::info('VideoController@index', [
            'user_id' => $user?->id,
            'route' => $request->route()?->getName(),
        ]);

        $businesses = $user->businesses()->pluck('id')->toArray();
        $videos = Video::whereIn('busniss_id', $businesses)->get();

        return response()->json($videos);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        Log::info('VideoController@store', [
            'user_id' => $user?->id,
            'payload' => $request->except(['video']),
        ]);

        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:102400',
            'busniss_id' => 'required|exists:busniss,id',
        ]);

        $file = $request->file('video');
        $filename = time().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('videos', $filename, 's3');

        $video = Video::create([
            'busniss_id' => $request->busniss_id,
            'url' => $path,
        ]);

        return response()->json([
            'message' => 'Video stored successfully',
            'video' => $video,
            'url' => Storage::disk('s3')->url($path),
        ], 201);
    }

    public function show(Video $video)
    {
        $user = auth()->user();

        Log::info('VideoController@show', [
            'user_id' => $user?->id,
            'video_id' => $video->id,
        ]);

        if ($video->business->user_id !== $user->id) {
            abort(403);
        }

        return response()->json(['video' => $video]);
    }

    public function destroy(Video $video)
    {
        $user = auth()->user();

        Log::info('VideoController@destroy', [
            'user_id' => $user?->id,
            'video_id' => $video->id,
        ]);

        if ($video->business->user_id !== $user->id) {
            abort(403);
        }

        if ($video->url && Storage::disk('s3')->exists($video->url)) {
            Storage::disk('s3')->delete($video->url);
        }

        $video->delete();

        return response()->json(['message' => 'Video deleted successfully'], 200);
    }

    public function update(Request $request, Video $video)
    {
        $user = auth()->user();

        Log::info('VideoController@update', [
            'user_id' => $user?->id,
            'video_id' => $video->id,
            'payload' => $request->except(['video']),
        ]);

        if ($video->business->user_id !== $user->id) {
            abort(403);
        }

        $rules = [
            'busniss_id' => 'nullable|exists:busniss,id',
        ];

        if ($request->hasFile('video')) {
            $rules['video'] = 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:102400';
        }

        $request->validate($rules);

        $data = [];

        if ($request->filled('busniss_id')) {
            $data['busniss_id'] = $request->busniss_id;
        }

        if ($request->hasFile('video')) {
            if ($video->url && Storage::disk('s3')->exists($video->url)) {
                Storage::disk('s3')->delete($video->url);
            }

            $file = $request->file('video');
            $filename = time().'_'.$file->getClientOriginalName();
            $data['url'] = $file->storeAs('videos', $filename, 's3');
        }

        $video->update($data);

        return response()->json([
            'message' => 'Video updated successfully',
            'video' => $video,
        ], 200);
    }
}
