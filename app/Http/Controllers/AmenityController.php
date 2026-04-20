<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use Illuminate\Http\JsonResponse;

class AmenityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Amenity::select('id', 'name', 'slug')->orderBy('name')->get());
    }
}
