<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\PlaybackStat;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $ids = $user->accessibleBusinessIds();

        $businesses = Business::whereIn('id', $ids)
            ->withCount(['screens', 'playlists', 'videos'])
            ->orderBy('name')
            ->get()
            ->map(fn (Business $business) => [
                ...$business->toArray(),
                'role' => $business->isOwnedBy($user) ? 'owner' : 'member',
            ]);

        return Inertia::render('dashboard', [
            'businesses' => $businesses,
            'unpairedScreens' => $this->unpairedScreens(),
        ]);
    }

    public function show(Request $request, Business $business): Response
    {
        if (! $business->isAccessibleBy($request->user())) {
            abort(403);
        }

        $business->loadCount(['screens', 'playlists', 'videos']);

        return Inertia::render('businesses/show', [
            'business' => $business,
            'isOwner' => $business->isOwnedBy($request->user()),
            'members' => $business->members()->orderBy('name')->get(['users.id', 'users.name', 'users.email']),
            'screens' => $business->screens()->with('screenPlaylists.videos')->orderBy('name')->get(),
            'playlists' => $business->playlists()->with('videos')->orderBy('name')->get(),
            'videos' => $business->videos()->orderByDesc('created_at')->get(),
            'unpairedScreens' => $this->unpairedScreens(),
            'activity' => AuditLog::query()
                ->where('busniss_id', $business->id)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'user_id', 'action', 'subject_type', 'subject_id', 'meta', 'created_at']),
        ]);
    }

    public function analytics(Request $request, Business $business): Response
    {
        if (! $business->isAccessibleBy($request->user())) {
            abort(403);
        }

        $today = now()->toDateString();
        $monthAgo = now()->subDays(30)->toDateString();

        $stats = fn () => PlaybackStat::query()->where('busniss_id', $business->id);

        $totalSeconds = $stats()->where('date', '>=', $monthAgo)->sum('seconds');
        $todaySeconds = $stats()->where('date', $today)->sum('seconds');

        $daily = $stats()
            ->select('date', DB::raw('SUM(seconds) as seconds'))
            ->where('date', '>=', now()->subDays(14)->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $perScreen = $stats()
            ->select('screen_id', DB::raw('SUM(seconds) as seconds'))
            ->where('date', '>=', $monthAgo)
            ->groupBy('screen_id')
            ->with('screen:id,name')
            ->orderByDesc('seconds')
            ->get()
            ->map(fn (PlaybackStat $stat) => [
                'name' => $stat->screen?->name ?? 'Removed screen',
                'seconds' => (int) $stat->seconds,
            ]);

        $perVideo = $stats()
            ->select('video_id', DB::raw('SUM(seconds) as seconds'))
            ->where('date', '>=', $monthAgo)
            ->groupBy('video_id')
            ->with('video:id,name')
            ->orderByDesc('seconds')
            ->get()
            ->map(fn (PlaybackStat $stat) => [
                'name' => $stat->video?->name ?? 'Removed video',
                'seconds' => (int) $stat->seconds,
            ]);

        return Inertia::render('businesses/analytics', [
            'business' => $business,
            'totalSeconds' => (int) $totalSeconds,
            'todaySeconds' => (int) $todaySeconds,
            'daily' => $daily,
            'perScreen' => $perScreen,
            'perVideo' => $perVideo,
        ]);
    }

    public function screens(Request $request): Response
    {
        $screens = Screen::whereIn('busniss_id', $request->user()->accessibleBusinessIds())
            ->with(['business:id,name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('screens/overview', [
            'screens' => $screens,
        ]);
    }

    public function storage(Request $request): Response
    {
        $ids = $request->user()->accessibleBusinessIds();

        $businesses = Business::whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->map(fn (Business $business) => [
                'id' => $business->id,
                'name' => $business->name,
                'videos_count' => $business->videos()->count(),
                'bytes' => (int) $business->videos()->sum('size'),
            ]);

        return Inertia::render('storage/overview', [
            'businesses' => $businesses,
            'totalBytes' => $businesses->sum('bytes'),
            'totalVideos' => $businesses->sum('videos_count'),
        ]);
    }

    /**
     * @return Collection<int, Screen>
     */
    protected function unpairedScreens()
    {
        return Screen::whereNotNull('pairing_code')
            ->where('pairing_code_expires_at', '>', now())
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();
    }
}
