<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Property::with(['location', 'images' => fn ($q) => $q->where('is_cover', true), 'user:id,name,role,avatar'])
            ->where('status', 'published');

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->filled('min_surface')) {
            $query->where('surface', '>=', $request->integer('min_surface'));
        }
        if ($request->filled('max_surface')) {
            $query->where('surface', '<=', $request->integer('max_surface'));
        }
        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', $request->integer('bedrooms'));
        }
        if ($request->filled('amenities')) {
            $ids = array_filter(explode(',', $request->amenities));
            if (! empty($ids)) {
                foreach ($ids as $amenityId) {
                    $query->whereHas('amenities', fn ($q) => $q->where('amenities.id', $amenityId));
                }
            }
        }

        $perPage = min((int) ($request->per_page ?? 20), 500);

        return response()->json($query->latest()->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        $property = Property::with(['location', 'images', 'amenities', 'user:id,name,role,avatar,phone'])
            ->where('id', $id)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json($property);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'required|integer|min:0',
            'transaction_type' => 'required|in:sale,rent',
            'property_type'    => 'required|in:apartment,villa,house,land,commercial,office',
            'status'           => 'in:draft,published',
            'location_id'      => 'nullable|exists:locations,id',
            'address'          => 'nullable|string|max:255',
            'latitude'         => 'nullable|numeric',
            'longitude'        => 'nullable|numeric',
            'surface'          => 'nullable|integer|min:0',
            'bedrooms'         => 'nullable|integer|min:0',
            'bathrooms'        => 'nullable|integer|min:0',
            'floor'            => 'nullable|integer',
            'is_furnished'     => 'boolean',
            'amenity_ids'      => 'nullable|array',
            'amenity_ids.*'    => 'exists:amenities,id',
            'images'           => 'nullable|array|max:10',
            'images.*.url'     => 'required|url',
            'images.*.is_cover'=> 'boolean',
        ]);

        $property = $request->user()->properties()->create($data);

        if (! empty($data['amenity_ids'])) {
            $property->amenities()->sync($data['amenity_ids']);
        }

        if (! empty($data['images'])) {
            foreach ($data['images'] as $index => $image) {
                $property->images()->create([
                    'url'      => $image['url'],
                    'position' => $index,
                    'is_cover' => $image['is_cover'] ?? $index === 0,
                ]);
            }
        }

        return response()->json($property->load(['images', 'amenities']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $property = $request->user()->properties()->findOrFail($id);

        $data = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'price'            => 'sometimes|integer|min:0',
            'transaction_type' => 'sometimes|in:sale,rent',
            'property_type'    => 'sometimes|in:apartment,villa,house,land,commercial,office',
            'status'           => 'sometimes|in:draft,published,sold,rented',
            'location_id'      => 'nullable|exists:locations,id',
            'address'          => 'nullable|string|max:255',
            'latitude'         => 'nullable|numeric',
            'longitude'        => 'nullable|numeric',
            'surface'          => 'nullable|integer|min:0',
            'bedrooms'         => 'nullable|integer|min:0',
            'bathrooms'        => 'nullable|integer|min:0',
            'floor'            => 'nullable|integer',
            'is_furnished'     => 'boolean',
            'amenity_ids'      => 'nullable|array',
            'amenity_ids.*'    => 'exists:amenities,id',
            'images'           => 'nullable|array|max:10',
            'images.*.url'     => 'required|url',
            'images.*.is_cover'=> 'boolean',
        ]);

        $property->update($data);

        if (array_key_exists('amenity_ids', $data)) {
            $property->amenities()->sync($data['amenity_ids'] ?? []);
        }

        if (array_key_exists('images', $data)) {
            $property->images()->delete();
            foreach ($data['images'] as $index => $image) {
                $property->images()->create([
                    'url'      => $image['url'],
                    'position' => $index,
                    'is_cover' => $image['is_cover'] ?? $index === 0,
                ]);
            }
        }

        return response()->json($property->load(['images', 'amenities']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->properties()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function myProperties(Request $request): JsonResponse
    {
        $properties = $request->user()
            ->properties()
            ->with(['location', 'images' => fn ($q) => $q->where('is_cover', true)])
            ->latest()
            ->paginate(20);

        return response()->json($properties);
    }
}
