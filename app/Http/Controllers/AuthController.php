<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        return $this->issueTokenResponse($user, $request);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        return $this->issueTokenResponse($user, $request);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()
            ->json(['message' => 'Déconnecté'])
            ->withCookie(cookie()->forget('sakan_token'));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    private function issueTokenResponse(User $user, Request $request): JsonResponse
    {
        $user->tokens()->where('name', 'sakan-web')->delete();
        $token = $user->createToken('sakan-web')->plainTextToken;

        // Identity stitching: link anonymous visitor activity to the authenticated user
        $visitorKey = $request->input('visitor_key');
        if ($visitorKey && Str::isUuid($visitorKey)) {
            DB::table('visitor_identities')->insertOrIgnore([
                'visitor_key' => $visitorKey,
                'user_id'     => $user->id,
                'stitched_at' => now(),
            ]);

            DB::table('property_views')
                ->where('visitor_key', $visitorKey)
                ->whereNull('user_id')
                ->update(['user_id' => $user->id]);
        }

        return response()
            ->json(['user' => $user])
            ->withCookie(cookie(
                'sakan_token',
                $token,
                60 * 24 * 30,
                '/',
                null,
                true,
                true,
                false,
                'none'
            ));
    }
}
