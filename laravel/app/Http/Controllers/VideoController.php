<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    protected string $disk = 'public';

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
            'name' => 'nullable|string|max:255',
        ]);

        $this->ensureBusinessOwned($user, $request->busniss_id);

        $file = $request->file('video');
        $filename = (string) Str::uuid().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('videos', $filename, $this->disk);

        $video = Video::create([
            'busniss_id' => $request->busniss_id,
            'name' => $request->name ?? $file->getClientOriginalName(),
            'url' => $path,
        ]);

        return response()->json([
            'message' => 'Video stored successfully',
            'video' => $video,
            'url' => Storage::disk($this->disk)->url($path),
        ], 201);
    }

    public function show(Video $video)
    {
        $user = auth()->user();

        Log::info('VideoController@show', [
            'user_id' => $user?->id,
            'video_id' => $video->id,
        ]);

        $this->authorizeVideo($user, $video);

        return response()->json(['video' => $video]);
    }

    public function destroy(Video $video)
    {
        $user = auth()->user();

        Log::info('VideoController@destroy', [
            'user_id' => $user?->id,
            'video_id' => $video->id,
        ]);

        $this->authorizeVideo($user, $video);

        if ($video->url && Storage::disk($this->disk)->exists($video->url)) {
            Storage::disk($this->disk)->delete($video->url);
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

        $this->authorizeVideo($user, $video);

        $rules = [
            'busniss_id' => 'nullable|exists:busniss,id',
            'name' => 'nullable|string|max:255',
        ];

        if ($request->hasFile('video')) {
            $rules['video'] = 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:102400';
        }

        $request->validate($rules);

        $data = [];

        if ($request->filled('name')) {
            $data['name'] = $request->name;
        }

        if ($request->filled('busniss_id')) {
            $this->ensureBusinessOwned($user, $request->busniss_id);
            $data['busniss_id'] = $request->busniss_id;
        }

        if ($request->hasFile('video')) {
            if ($video->url && Storage::disk($this->disk)->exists($video->url)) {
                Storage::disk($this->disk)->delete($video->url);
            }

            $file = $request->file('video');
            $filename = (string) Str::uuid().'_'.$file->getClientOriginalName();
            $data['url'] = $file->storeAs('videos', $filename, $this->disk);

            if (! isset($data['name'])) {
                $data['name'] = $file->getClientOriginalName();
            }
        }

        $video->update($data);

        return response()->json([
            'message' => 'Video updated successfully',
            'video' => $video,
        ], 200);
    }

    protected function authorizeVideo($user, Video $video): void
    {
        if (! $video->business) {
            abort(404);
        }

        if ($video->business->user_id !== $user->id) {
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
