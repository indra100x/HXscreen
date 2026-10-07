<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Stream a video file behind a signed, expiring URL. No throttle here:
     * players issue a request per range chunk, which throttling would break.
     */
    public function show(Request $request, Video $video)
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($video->url)) {
            abort(404);
        }

        return response()->file($disk->path($video->url));
    }
}
