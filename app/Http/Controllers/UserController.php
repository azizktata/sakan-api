<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:30',
            'avatar'=> 'nullable|url',
        ]);

        $request->user()->update($data);

        return response()->json($request->user()->fresh());
    }
}
