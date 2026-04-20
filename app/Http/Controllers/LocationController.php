<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Location::select('id', 'name', 'slug')->orderBy('name')->get());
    }
}
