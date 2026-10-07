<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\PlaybackStat;
use App\Models\Playlist;
use App\Models\Screen;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScreenController extends Controller
{
    private const DEVICE_TOKEN_TTL_DAYS = 30;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::info('ScreenController@index', [
            'user_id' => $user?->id,
            'route' => $request->route()?->getName(),
        ]);

        $businessIds = $this->accessibleBusinessIds($user);

        $screens = Screen::whereIn('busniss_id', $businessIds)->get();

        return response()->json($screens);
    }

    public function store(Request $request): JsonResponse
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

        $this->ensureBusinessAccess($user, $request->busniss_id);

        $screen = Screen::create([
            'name' => $request->name,
            'busniss_id' => $request->busniss_id,
            'device_id' => $request->device_id,
        ]);

        AuditLog::record($user, 'screen.created', $screen->busniss_id, $screen, [], $request->ip());

        return response()->json([
            'message' => 'Screen created successfully',
            'screen' => $screen,
        ], 201);
    }

    /**
     * Screens waiting to be (re-)paired: a live pairing code, whether the
     * screen is brand new or already owned. Only identifiers are exposed —
     * never codes or tokens — so one account cannot hijack another
     * account's TV. The physical code on the TV stays the proof.
     */
    public function unpaired(): JsonResponse
    {
        return response()->json(
            Screen::whereNotNull('pairing_code')
                ->where('pairing_code_expires_at', '>', now())
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get()
        );
    }

    public function show(Screen $screen): JsonResponse
    {
        $user = auth()->user();

        Log::info('ScreenController@show', [
            'user_id' => $user?->id,
            'screen_id' => $screen->id,
        ]);

        $this->authorizeScreen($user, $screen);

        return response()->json(['screen' => $screen]);
    }

    public function destroy(Screen $screen): JsonResponse
    {
        $user = auth()->user();

        Log::info('ScreenController@destroy', [
            'user_id' => $user?->id,
            'screen_id' => $screen->id,
        ]);

        $this->authorizeScreen($user, $screen);

        $screen->delete();

        AuditLog::record($user, 'screen.deleted', $screen->busniss_id, $screen);

        return response()->json(['message' => 'Screen deleted successfully'], 200);
    }

    public function update(Request $request, Screen $screen): JsonResponse
    {
        $user = auth()->user();

        Log::info('ScreenController@update', [
            'user_id' => $user?->id,
            'screen_id' => $screen->id,
            'payload' => $request->only(['name', 'device_id', 'busniss_id', 'playback_mode']),
        ]);

        $this->authorizeScreen($user, $screen);

        $request->validate([
            'name' => 'sometimes|required|string|max:50',
            'device_id' => 'sometimes|required|string|max:255',
            'busniss_id' => 'sometimes|required|exists:busniss,id',
            'playback_mode' => 'sometimes|required|in:loop,once',
        ]);

        if ($request->filled('busniss_id')) {
            $this->ensureBusinessAccess($user, $request->busniss_id);
        }

        $screen->update($request->only(['name', 'device_id', 'busniss_id', 'playback_mode']));

        AuditLog::record($user, 'screen.updated', $screen->busniss_id, $screen, [], $request->ip());

        return response()->json([
            'message' => 'Screen updated successfully',
            'screen' => $screen,
        ], 200);
    }

    public function requestPairingCode(Request $request): JsonResponse
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
        ]);

        return response()->json([
            'device_id' => $screen->device_id,
            'pairing_code' => $screen->pairing_code,
            'expires_in_seconds' => 600,
        ], 200);
    }

    public function pair(Request $request): JsonResponse
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

        if (! $this->pairingCodeMatches($screen, $request->pairing_code)) {
            return response()->json(['message' => 'Invalid pairing code'], 422);
        }

        if ($screen->pairing_code_expires_at && $screen->pairing_code_expires_at->isPast()) {
            return response()->json(['message' => 'Pairing code expired'], 422);
        }

        $rawToken = $this->issueDeviceToken($screen);

        $screen->busniss_id = $business->id;
        $screen->paired_at = now();
        $screen->pairing_code = null;
        $screen->pairing_code_expires_at = null;
        $screen->save();

        Log::info('ScreenController@pair', [
            'user_id' => $user->id,
            'device_id' => $screen->device_id,
            'busniss_id' => $screen->busniss_id,
        ]);

        AuditLog::record($user, 'screen.paired', $screen->busniss_id, $screen, [], $request->ip());

        return response()->json([
            'message' => 'Screen paired successfully',
            'device_id' => $screen->device_id,
            'busniss_id' => $screen->busniss_id,
            'device_token' => $rawToken,
        ], 200);
    }

    public function heartbeat(Request $request, Screen $screen): JsonResponse
    {
        $token = $this->deviceTokenFrom($request);

        Log::info('ScreenController@heartbeat', [
            'screen_id' => $screen->id,
            'device_token_present' => filled($token),
        ]);

        if (! $token || ! $screen->device_token || ! hash_equals($screen->device_token, hash('sha256', $token))) {
            return $this->unauthorized();
        }

        if ($this->deviceTokenIsExpired($screen)) {
            return $this->unauthorized('Device token expired');
        }

        $request->validate([
            'video_id' => 'nullable|string|max:255',
            'position_ms' => 'nullable|integer|min:0',
            'is_playing' => 'nullable|boolean',
        ]);

        $previousSeenAt = $screen->last_seen_at;
        $screen->last_seen_at = now();

        if ($request->filled('video_id')) {
            $this->accumulatePlayTime($screen, $previousSeenAt, $request->video_id, $request->boolean('is_playing', true));

            $screen->current_video_id = $request->video_id;
            $screen->current_position_ms = $request->input('position_ms', 0);
            $screen->position_reported_at = now();
        }

        if ($request->has('is_playing')) {
            $screen->is_playing = $request->boolean('is_playing');
        }

        if ($request->filled('ack_command_id') && ($screen->pending_command['id'] ?? null) === $request->ack_command_id) {
            $screen->pending_command = null;
        }

        $screen->save();

        return response()->json([
            'message' => 'Heartbeat received successfully',
            'screen' => $screen,
        ], 200);
    }

    public function getPlaylists(Screen $screen): JsonResponse
    {
        $user = auth()->user();

        $this->authorizeScreen($user, $screen);

        return response()->json($screen->screenPlaylists()->with('videos')->get());
    }

    public function attachPlaylist(Request $request, Screen $screen): JsonResponse
    {
        $user = auth()->user();

        $this->authorizeScreen($user, $screen);

        $request->validate([
            'playlist_id' => 'required|exists:playlist,id',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
        ]);

        $playlist = Playlist::findOrFail($request->playlist_id);

        if (! $playlist->business || $playlist->business->user_id !== $user->id) {
            abort(403);
        }

        if ($playlist->busniss_id !== $screen->busniss_id) {
            return response()->json(['message' => 'Playlist belongs to a different business'], 422);
        }

        if ($screen->screenPlaylists()->where('playlist.id', $playlist->id)->exists()) {
            return response()->json(['message' => 'Playlist already assigned to screen'], 409);
        }

        $screen->screenPlaylists()->attach($playlist->id, [
            'id' => (string) Str::uuid(),
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        Log::info('ScreenController@attachPlaylist', [
            'user_id' => $user->id,
            'screen_id' => $screen->id,
            'playlist_id' => $playlist->id,
        ]);

        AuditLog::record($user, 'screen.playlist_attached', $screen->busniss_id, $screen, [
            'playlist_id' => $playlist->id,
        ], $request->ip());

        return response()->json(['message' => 'Playlist assigned to screen successfully'], 200);
    }

    public function detachPlaylist(Request $request, Screen $screen): JsonResponse
    {
        $user = auth()->user();

        $this->authorizeScreen($user, $screen);

        $request->validate([
            'playlist_id' => 'required|exists:playlist,id',
        ]);

        $screen->screenPlaylists()->detach($request->playlist_id);

        Log::info('ScreenController@detachPlaylist', [
            'user_id' => $user->id,
            'screen_id' => $screen->id,
            'playlist_id' => $request->playlist_id,
        ]);

        AuditLog::record($user, 'screen.playlist_detached', $screen->busniss_id, $screen, [
            'playlist_id' => $request->playlist_id,
        ], $request->ip());

        return response()->json(['message' => 'Playlist removed from screen successfully'], 200);
    }

    public function updatePlaylistSchedule(Request $request, Screen $screen): JsonResponse
    {
        $user = auth()->user();

        $this->authorizeScreen($user, $screen);

        $request->validate([
            'playlist_id' => 'required|exists:playlist,id',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
        ]);

        if (! $screen->screenPlaylists()->where('playlist.id', $request->playlist_id)->exists()) {
            return response()->json(['message' => 'Playlist is not assigned to screen'], 422);
        }

        $screen->screenPlaylists()->updateExistingPivot($request->playlist_id, [
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        AuditLog::record($user, 'screen.schedule_updated', $screen->busniss_id, $screen, [
            'playlist_id' => $request->playlist_id,
        ], $request->ip());

        return response()->json(['message' => 'Schedule updated successfully'], 200);
    }

    /**
     * Called by the TV while it shows the pairing code. Returns whether the
     * screen has been paired yet, and hands over a device token exactly once
     * the dashboard completes pairing.
     */
    public function claimDevice(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|max:255',
            'pairing_code' => 'required|string|max:20',
        ]);

        $screen = Screen::where('device_id', $request->device_id)->first();

        if (! $screen) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        if ($this->pairingCodeMatches($screen, $request->pairing_code)) {
            if ($screen->pairing_code_expires_at && $screen->pairing_code_expires_at->isPast()) {
                return response()->json(['message' => 'Pairing code expired'], 422);
            }

            return response()->json(['paired' => false], 200);
        }

        if ($screen->device_token) {
            $rawToken = $this->issueDeviceToken($screen);
            $screen->last_seen_at = now();
            $screen->save();

            Log::info('ScreenController@claimDevice', [
                'device_id' => $screen->device_id,
            ]);

            return response()->json([
                'paired' => true,
                'device_id' => $screen->device_id,
                'device_token' => $rawToken,
                'expires_in_seconds' => self::DEVICE_TOKEN_TTL_DAYS * 24 * 60 * 60,
            ], 200);
        }

        return response()->json(['message' => 'Invalid pairing code'], 422);
    }

    /**
     * Rotate a valid device token. Authenticated by device token, not Sanctum.
     */
    public function refreshDeviceToken(Request $request): JsonResponse
    {
        $screen = $this->findScreenByToken($this->deviceTokenFrom($request));

        if (! $screen) {
            return $this->unauthorized();
        }

        if ($this->deviceTokenIsExpired($screen)) {
            return $this->unauthorized('Device token expired');
        }

        $rawToken = $this->issueDeviceToken($screen);
        $screen->last_seen_at = now();
        $screen->save();

        return response()->json([
            'device_id' => $screen->device_id,
            'device_token' => $rawToken,
            'expires_in_seconds' => self::DEVICE_TOKEN_TTL_DAYS * 24 * 60 * 60,
        ], 200);
    }

    /**
     * Queue a remote-control command for the TV: pause, resume, or seek by
     * a relative number of seconds. Replaces any previous pending command.
     */
    public function sendCommand(Request $request, Screen $screen): JsonResponse
    {
        $user = auth()->user();

        $this->authorizeScreen($user, $screen);

        $request->validate([
            'action' => 'required|in:pause,resume,seek_by',
            'arg' => 'nullable|integer|min:-3600|max:3600',
        ]);

        if ($request->action === 'seek_by' && ! $request->filled('arg')) {
            return response()->json(['message' => 'The arg field is required for seek_by.'], 422);
        }

        $screen->pending_command = [
            'id' => (string) Str::uuid(),
            'action' => $request->action,
            'arg' => $request->arg,
            'created_at' => now()->toDateTimeString(),
        ];
        $screen->save();

        Log::info('ScreenController@sendCommand', [
            'user_id' => $user->id,
            'screen_id' => $screen->id,
            'action' => $request->action,
        ]);

        AuditLog::record($user, 'screen.command_sent', $screen->busniss_id, $screen, [
            'action' => $request->action,
        ], $request->ip());

        return response()->json(['message' => 'Command queued'], 200);
    }

    /**
     * The TV's command inbox. Commands older than two minutes are dropped
     * so an offline TV never acts on stale input when it returns.
     */
    public function deviceCommands(Request $request): JsonResponse
    {
        $screen = $this->findScreenByToken($this->deviceTokenFrom($request));

        if (! $screen) {
            return $this->unauthorized();
        }

        if ($this->deviceTokenIsExpired($screen)) {
            return $this->unauthorized('Device token expired');
        }

        $command = $screen->pending_command;

        if (is_array($command) && isset($command['created_at']) && Carbon::parse($command['created_at'])->addMinutes(2)->isPast()) {
            $command = null;
            $screen->pending_command = null;
            $screen->save();
        }

        return response()->json(['command' => $command], 200);
    }

    /**
     * Content feed for a paired device. Authenticated by device token, not Sanctum.
     */
    public function deviceContent(Request $request): JsonResponse
    {
        $screen = $this->findScreenByToken($this->deviceTokenFrom($request));

        if (! $screen) {
            return $this->unauthorized();
        }

        if ($this->deviceTokenIsExpired($screen)) {
            return $this->unauthorized('Device token expired');
        }

        $screen->last_seen_at = now();
        $screen->save();

        $now = now();

        $playlists = $screen->screenPlaylists()
            ->where(function ($query) use ($now) {
                $query->whereNull('screen_playlist.start_time')
                    ->orWhere('screen_playlist.start_time', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('screen_playlist.end_time')
                    ->orWhere('screen_playlist.end_time', '>=', $now);
            })
            ->with('videos')
            ->get();

        return response()->json([
            'screen' => $screen,
            'playlists' => $playlists,
        ], 200);
    }

    /**
     * Credit the elapsed time since the previous heartbeat to today's
     * per-video aggregate. Only counts while the TV reports playing, and
     * each beat is capped so offline gaps never inflate the totals.
     */
    protected function accumulatePlayTime(Screen $screen, mixed $previousSeenAt, string $videoId, bool $playing): void
    {
        if (! $playing || ! $previousSeenAt instanceof \DateTimeInterface) {
            return;
        }

        $seconds = (int) min(abs(now()->diffInSeconds($previousSeenAt)), 120);

        if ($seconds <= 0) {
            return;
        }

        PlaybackStat::query()->updateOrCreate(
            [
                'screen_id' => $screen->id,
                'video_id' => $videoId,
                'date' => now()->toDateString(),
            ],
            ['busniss_id' => $screen->busniss_id]
        )->increment('seconds', $seconds);
    }

    protected function deviceTokenFrom(Request $request): ?string
    {
        $token = $request->bearerToken() ?: $request->input('device_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    protected function findScreenByToken(?string $token): ?Screen
    {
        if (! $token) {
            return null;
        }

        return Screen::where('device_token', hash('sha256', $token))->first();
    }

    protected function deviceTokenIsExpired(Screen $screen): bool
    {
        return $screen->device_token_expires_at && $screen->device_token_expires_at->isPast();
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }

    /**
     * Mint a fresh device token, storing only its hash. The raw token is
     * shown once and never stored.
     */
    protected function issueDeviceToken(Screen $screen): string
    {
        $rawToken = Str::random(64);

        $screen->device_token = hash('sha256', $rawToken);
        $screen->device_token_expires_at = now()->addDays(self::DEVICE_TOKEN_TTL_DAYS);

        return $rawToken;
    }

    protected function pairingCodeMatches(Screen $screen, ?string $code): bool
    {
        return $screen->pairing_code
            && $code
            && strtoupper($screen->pairing_code) === strtoupper($code);
    }

    protected function authorizeScreen($user, Screen $screen): void
    {
        if (! $screen->business || ! $screen->business->isAccessibleBy($user)) {
            abort($screen->business ? 403 : 404);
        }
    }
}
