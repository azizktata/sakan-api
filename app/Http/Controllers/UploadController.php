<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image'       => 'required|image|mimes:jpeg,png,webp|max:5120', // 5 MB
        ]);

        $file      = $request->file('image');
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('properties', $filename, 'uploads');

        return response()->json([
            'url' => Storage::disk('uploads')->url($path),
        ], 201);
    }
}
