<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;

class BusinessController extends Controller
{

 public function index(Request $request)
 {
     return response()->json($request->user()->businesses);
 }

  public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        Business::create([
            'user_id' => $user->id,
            'name' => $request->name,
        ]);

        return response()->json(['message' => 'Business created successfully', 'businesses' => $user->businesses], 201);
    }

    public function show(Business $business)
    {
        $user = auth()->user();

        if ($business->user_id !== $user->id) {
            abort(403);
        }

        return response()->json(['business' => $business]);
    }
    public function destroy(Business $business)
    {
        $user = auth()->user();

        if ($business->user_id !== $user->id) {
            abort(403);
        }

        $business->delete();

        return  response()->json(['message' => 'Business deleted successfully'],200);
    }
    public function update(Request $request, Business $business)
    {
        $user = auth()->user();

        if ($business->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $business->update([
            'name' => $request->name,
        ]);

        return response()->json(['message' => 'Business updated successfully', 'business' => $business], 200);
    }
}

