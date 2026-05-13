<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Location::select('id', 'name', 'slug', 'parent_id', 'zone_score', 'neighborhoods', 'latitude', 'longitude')
                ->orderBy('parent_id')
                ->orderBy('name')
                ->get()
                ->map(fn ($loc) => [
                    'id'            => $loc->id,
                    'name'          => $loc->name,
                    'slug'          => $loc->slug,
                    'parent_id'     => $loc->parent_id,
                    'zone_score'    => $loc->zone_score,
                    'neighborhoods' => $loc->neighborhoods ? json_decode($loc->neighborhoods) : [],
                    'latitude'      => $loc->latitude,
                    'longitude'     => $loc->longitude,
                ])
        );
    }
}
