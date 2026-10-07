<?php

namespace App\Http\Controllers;

use App\Models\Business;

abstract class Controller
{
    /**
     * IDs of every business the user owns or is a member of.
     *
     * @return list<string>
     */
    protected function accessibleBusinessIds(object $user): array
    {
        return $user->accessibleBusinessIds();
    }

    /**
     * 403 unless the user owns the business or is a member of it.
     */
    protected function ensureBusinessAccess(object $user, string $businessId): void
    {
        $business = Business::findOrFail($businessId);

        if (! $business->isAccessibleBy($user)) {
            abort(403);
        }
    }

    /**
     * 403 unless the user owns the business. Ownership alone grants
     * member management and business deletion.
     */
    protected function ensureBusinessOwner(object $user, Business $business): void
    {
        if (! $business->isOwnedBy($user)) {
            abort(403);
        }
    }
}
