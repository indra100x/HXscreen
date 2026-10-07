<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    protected string $disk = 'r2';

    private const VIDEO_FILE_RULE = 'file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:102400';

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::info('VideoController@index', [
            'user_id' => $user?->id,
            'route' => $request->route()?->getName(),
        ]);

        $businesses = $this->accessibleBusinessIds($user);
        $videos = Video::whereIn('busniss_id', $businesses)->get();

        return response()->json($videos);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::info('VideoController@store', [
            'user_id' => $user?->id,
            'payload' => $request->except(['video']),
        ]);

        $request->validate([
            'video' => 'required|'.self::VIDEO_FILE_RULE,
            'busniss_id' => 'required|exists:busniss,id',
            'name' => 'nullable|string|max:255',
        ]);

        $this->ensureBusinessAccess($user, $request->busniss_id);

        $file = $request->file('video');
        $filename = (string) Str::uuid().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('videos', $filename, $this->disk);

        $video = Video::create([
            'busniss_id' => $request->busniss_id,
            'disk' => $this->disk,
            'name' => $request->name ?? $file->getClientOriginalName(),
            'url' => $path,
            'size' => $file->getSize(),
        ]);

        AuditLog::record($user, 'video.created', $video->busniss_id, $video, [], $request->ip());

        return response()->json([
            'message' => 'Video stored successfully',
            'video' => $video,
            'url' => $video->media_url,
        ], 201);
    }

    public function show(Video $video): JsonResponse
    {
        $user = auth()->user();

        Log::info('VideoController@show', [
            'user_id' => $user?->id,
            'video_id' => $video->id,
        ]);

        $this->authorizeVideo($user, $video);

        return response()->json(['video' => $video]);
    }

    public function destroy(Video $video): JsonResponse
    {
        $user = auth()->user();

        Log::info('VideoController@destroy', [
            'user_id' => $user?->id,
            'video_id' => $video->id,
        ]);

        $this->authorizeVideo($user, $video);

        $this->deleteStoredFile($video);

        $video->delete();

        AuditLog::record($user, 'video.deleted', $video->busniss_id, $video);

        return response()->json(['message' => 'Video deleted successfully'], 200);
    }

    public function update(Request $request, Video $video): JsonResponse
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
            $rules['video'] = 'required|'.self::VIDEO_FILE_RULE;
        }

        $request->validate($rules);

        $data = [];

        if ($request->filled('name')) {
            $data['name'] = $request->name;
        }

        if ($request->filled('busniss_id')) {
            $this->ensureBusinessAccess($user, $request->busniss_id);
            $data['busniss_id'] = $request->busniss_id;
        }

        if ($request->hasFile('video')) {
            $this->deleteStoredFile($video);

            $file = $request->file('video');
            $filename = (string) Str::uuid().'_'.$file->getClientOriginalName();
            $data['url'] = $file->storeAs('videos', $filename, $this->disk);
            $data['disk'] = $this->disk;
            $data['size'] = $file->getSize();

            if (! isset($data['name'])) {
                $data['name'] = $file->getClientOriginalName();
            }
        }

        $video->update($data);

        AuditLog::record($user, 'video.updated', $video->busniss_id, $video, [], $request->ip());

        return response()->json([
            'message' => 'Video updated successfully',
            'video' => $video,
        ], 200);
    }

    protected function deleteStoredFile(Video $video): void
    {
        $disk = (string) $video->disk;

        if ($video->url && Storage::disk($disk)->exists($video->url)) {
            Storage::disk($disk)->delete($video->url);
        }
    }

    protected function authorizeVideo($user, Video $video): void
    {
        if (! $video->business || ! $video->business->isAccessibleBy($user)) {
            abort($video->business ? 403 : 404);
        }
    }
}
