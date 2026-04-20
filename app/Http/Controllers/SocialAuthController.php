<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/auth?error=google_failed');
        }

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name'     => $googleUser->getName(),
                'email'    => $googleUser->getEmail(),
                'avatar'   => $googleUser->getAvatar(),
                'provider' => 'google',
                'password' => null,
            ]
        );

        $user->tokens()->where('name', 'sakan-web')->delete();
        $token = $user->createToken('sakan-web')->plainTextToken;

        return redirect(env('FRONTEND_URL') . '/auth/callback')
            ->withCookie(cookie('sakan_token', $token, 60 * 24 * 30, '/', null, true, true, false, 'none'));
    }
}
