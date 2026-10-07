<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $businesses = Business::whereIn('id', $this->accessibleBusinessIds($request->user()))
            ->orderBy('name')
            ->get()
            ->map(fn (Business $business) => [
                ...$business->toArray(),
                'role' => $business->isOwnedBy($request->user()) ? 'owner' : 'member',
            ]);

        return response()->json($businesses);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Single-business package: one venue per account. The owner's venue
        // is normally seeded at install (app:ensure-owner); this guard stops
        // extra venues being created through the setup screen or API.
        if ($user->businesses()->exists() || $user->memberBusinesses()->exists()) {
            return response()->json(['message' => 'This panel manages a single venue.'], 422);
        }

        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $business = Business::create([
            'user_id' => $user->id,
            'name' => $request->name,
        ]);

        AuditLog::record($user, 'business.created', $business->id, $business, [], $request->ip());

        return response()->json(['message' => 'Business created successfully', 'businesses' => $user->businesses], 201);
    }

    public function show(Business $business)
    {
        $user = auth()->user();

        if (! $business->isAccessibleBy($user)) {
            abort(403);
        }

        return response()->json([
            'business' => [
                ...$business->toArray(),
                'role' => $business->isOwnedBy($user) ? 'owner' : 'member',
            ],
        ]);
    }

    public function destroy(Request $request, Business $business)
    {
        $user = auth()->user();

        $this->ensureBusinessOwner($user, $business);

        $business->delete();

        AuditLog::record($user, 'business.deleted', $business->id, $business, [], $request->ip());

        return response()->json(['message' => 'Business deleted successfully'], 200);
    }

    public function update(Request $request, Business $business)
    {
        $user = auth()->user();

        $this->ensureBusinessOwner($user, $business);

        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $business->update([
            'name' => $request->name,
        ]);

        AuditLog::record($user, 'business.updated', $business->id, $business, [], $request->ip());

        return response()->json(['message' => 'Business updated successfully', 'business' => $business], 200);
    }

    public function members(Business $business)
    {
        $user = auth()->user();

        if (! $business->isAccessibleBy($user)) {
            abort(403);
        }

        return response()->json(
            $business->members()->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
        );
    }

    public function inviteMember(Request $request, Business $business)
    {
        $user = auth()->user();

        $this->ensureBusinessOwner($user, $business);

        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $member = User::where('email', $request->email)->first();

        // New address: the owner creates the teammate's login on the spot
        // (there is no public registration in the single-business package).
        if (! $member) {
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => ['required', 'string', Password::default(), 'confirmed'],
            ]);

            $member = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
            ]);
            $member->forceFill(['email_verified_at' => now()])->save();

            $business->members()->attach($member->id, ['id' => (string) Str::uuid()]);

            AuditLog::record($user, 'business.member_created', $business->id, $member, [], $request->ip());

            return response()->json(['message' => 'Member account created', 'member' => $member->only(['id', 'name', 'email'])], 201);
        }

        if ($business->isOwnedBy($member)) {
            return response()->json(['message' => 'User already owns this business'], 422);
        }

        if ($business->members()->where('users.id', $member->id)->exists()) {
            return response()->json(['message' => 'User is already a member'], 409);
        }

        $business->members()->attach($member->id, ['id' => (string) Str::uuid()]);

        AuditLog::record($user, 'business.member_invited', $business->id, $member, [], $request->ip());

        return response()->json(['message' => 'Member added successfully'], 200);
    }

    public function removeMember(Request $request, Business $business, User $member)
    {
        $user = auth()->user();

        $this->ensureBusinessOwner($user, $business);

        if ($business->isOwnedBy($member)) {
            return response()->json(['message' => 'The owner cannot be removed'], 422);
        }

        $business->members()->detach($member->id);

        AuditLog::record($user, 'business.member_removed', $business->id, $member, [], $request->ip());

        return response()->json(['message' => 'Member removed successfully'], 200);
    }
}
