<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'email' => 'required|email|exists:users,email',
        ]);

        $member = User::where('email', $request->email)->firstOrFail();

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
