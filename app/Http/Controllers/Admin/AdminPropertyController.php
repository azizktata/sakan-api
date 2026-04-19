<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Property::with(['user:id,name,email', 'location']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(30));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $property = Property::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:draft,published,sold,rented',
        ]);

        $property->update($data);

        return response()->json($property);
    }

    public function destroy(int $id): JsonResponse
    {
        Property::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
