<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $businesses = $request->user()->businesses()
            ->withCount(['screens', 'playlists', 'videos'])
            ->orderBy('name')
            ->get();

        return Inertia::render('dashboard', [
            'businesses' => $businesses,
            'unpairedScreens' => $this->unpairedScreens(),
        ]);
    }

    public function show(Request $request, Business $business): Response
    {
        if ($business->user_id !== $request->user()->id) {
            abort(403);
        }

        $business->loadCount(['screens', 'playlists', 'videos']);

        return Inertia::render('businesses/show', [
            'business' => $business,
            'screens' => $business->screens()->with('screenPlaylists.videos')->orderBy('name')->get(),
            'playlists' => $business->playlists()->with('videos')->orderBy('name')->get(),
            'videos' => $business->videos()->orderByDesc('created_at')->get(),
            'unpairedScreens' => $this->unpairedScreens(),
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
