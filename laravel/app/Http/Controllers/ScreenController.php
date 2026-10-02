<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Screen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScreenController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        Log::info('ScreenController@index', [
            'user_id' => $user?->id,
            'route' => $request->route()?->getName(),
        ]);

        $businessIds = $user->businesses()->pluck('id')->toArray();

        $screens = Screen::whereIn('busniss_id', $businessIds)->get();

        return response()->json($screens);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        Log::info('ScreenController@store', [
            'user_id' => $user?->id,
            'payload' => $request->only(['name', 'busniss_id', 'device_id']),
        ]);

        $request->validate([
            'name' => 'required|string|max:50',
            'busniss_id' => 'required|exists:busniss,id',
            'device_id' => 'required|string|max:255',
        ]);

        $screen = Screen::create([
            'name' => $request->name,
            'busniss_id' => $request->busniss_id,
            'device_id' => $request->device_id,
        ]);

        return response()->json([
            'message' => 'Screen created successfully',
            'screen' => $screen,
        ], 201);
    }

    public function show(Screen $screen)
    {
        $user = auth()->user();

        Log::info('ScreenController@show', [
            'user_id' => $user?->id,
            'screen_id' => $screen->id,
        ]);

        if ($screen->business->user_id !== $user->id) {
            abort(403);
        }

        return response()->json(['screen' => $screen]);
    }

    public function destroy(Screen $screen)
    {
        $user = auth()->user();

        Log::info('ScreenController@destroy', [
            'user_id' => $user?->id,
            'screen_id' => $screen->id,
        ]);

        if ($screen->business->user_id !== $user->id) {
            abort(403);
        }

        $screen->delete();

        return response()->json(['message' => 'Screen deleted successfully'], 200);
    }

    public function update(Request $request, Screen $screen)
    {
        $user = auth()->user();

        Log::info('ScreenController@update', [
            'user_id' => $user?->id,
            'screen_id' => $screen->id,
            'payload' => $request->only(['name', 'device_id', 'busniss_id']),
        ]);

        if ($screen->business->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:50',
            'device_id' => 'sometimes|required|string|max:255',
            'busniss_id' => 'sometimes|required|exists:busniss,id',
        ]);

        $screen->update($request->only(['name', 'device_id', 'busniss_id']));

        return response()->json([
            'message' => 'Screen updated successfully',
            'screen' => $screen,
        ], 200);
    }

    public function requestPairingCode(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:255',
            'name' => 'nullable|string|max:50',
        ]);

        $screen = Screen::firstOrCreate(
            ['device_id' => $request->device_id],
            [
                'name' => $request->name ?? 'Android TV',
                'busniss_id' => null,
            ]
        );

        if (! empty($screen->device_token)) {
            $screen->device_token = null;
            $screen->device_token_expires_at = null;
        }

        $screen->pairing_code = strtoupper(Str::random(6));
        $screen->pairing_code_expires_at = now()->addMinutes(10);
        $screen->paired_at = null;
        $screen->save();

        Log::info('ScreenController@requestPairingCode', [
            'device_id' => $screen->device_id,
            'pairing_code' => $screen->pairing_code,
        ]);

        return response()->json([
            'device_id' => $screen->device_id,
            'pairing_code' => $screen->pairing_code,
            'expires_in_seconds' => 600,
        ], 200);
    }

    public function pair(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'device_id' => 'required|string|max:255',
            'pairing_code' => 'required|string|max:20',
            'busniss_id' => 'required|exists:busniss,id',
        ]);

        $business = Business::findOrFail($request->busniss_id);

        if ($business->user_id !== $user->id) {
            abort(403);
        }

        $screen = Screen::where('device_id', $request->device_id)->first();

        if (! $screen) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        if (! $screen->pairing_code || strtoupper($screen->pairing_code) !== strtoupper($request->pairing_code)) {
            return response()->json(['message' => 'Invalid pairing code'], 422);
        }

        if ($screen->pairing_code_expires_at && $screen->pairing_code_expires_at->isPast()) {
            return response()->json(['message' => 'Pairing code expired'], 422);
        }

        $screen->busniss_id = $business->id;
        $screen->paired_at = now();
        $screen->device_token = hash('sha256', Str::random(64));
        $screen->device_token_expires_at = now()->addDays(30);
        $screen->pairing_code = null;
        $screen->pairing_code_expires_at = null;
        $screen->save();

        Log::info('ScreenController@pair', [
            'user_id' => $user->id,
            'device_id' => $screen->device_id,
            'busniss_id' => $screen->busniss_id,
        ]);

        return response()->json([
            'message' => 'Screen paired successfully',
            'device_id' => $screen->device_id,
            'busniss_id' => $screen->busniss_id,
            'device_token' => $screen->device_token,
        ], 200);
    }

    public function heartbeat(Request $request)
    {
        $token = $request->bearerToken() ?: $request->input('device_token');

        Log::info('ScreenController@heartbeat', [
            'device_token_present' => filled($token),
        ]);

        if (! $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $screen = Screen::where('device_token', hash('sha256', $token))->first();

        if (! $screen) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($screen->device_token_expires_at && $screen->device_token_expires_at->isPast()) {
            return response()->json(['message' => 'Device token expired'], 401);
        }

        $screen->last_seen_at = now();
        $screen->save();

        return response()->json([
            'message' => 'Heartbeat received successfully',
            'screen' => $screen,
        ], 200);
    }
}
