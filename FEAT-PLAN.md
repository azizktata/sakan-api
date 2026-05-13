# SAKAN project

SAKAN — سكن · Project Reference

> Plateforme immobilière éthique & halal — Tunisie
>
> Stack: Next.js · Tailwind · Laravel · MySQL

## Architecture Overview

**Séparation stricte frontend / backend.**

Laravel est la seule source de vérité (auth, data, business logic).

Next.js est un client pur — il ne touche jamais la DB directement.

```
Next.js (web)          Mobile app (future)
       ↓                       ↓
       ↓   Bearer token (Sanctum opaque)
       ↓                       ↓
    Laravel API (Sanctum + Socialite + business logic)
              ↓
         PostgreSQL ||  MYSQL

```

4. **Fonctionnalités Clés :**
   * **Espace Client :** Dashboard "Pro-level" avec gestion des annonces.
   * **Dépôt d'annonce :** Flow simplifié avec barre de progression.
   * **Espace Admin :** Modération et statistiques.

## Stack

### Backend

| Outil               | Usage                                        |
| ------------------- | -------------------------------------------- |
| Laravel             | API REST — auth + business logic            |
| Laravel Sanctum     | API tokens — web + mobile                   |
| Laravel Socialite   | Google OAuth (consommateur, pas fournisseur) |
| PostgreSQL OR MySQL | Base de données principale                  |
|                     |                                              |
|                     |                                              |

### Frontend

| Outil                | Usage                                   |
| -------------------- | --------------------------------------- |
| Next.js (App Router) | UI web                                  |
| axios                | Appels API HTTP                         |
| Gestion token        | httpOnly cookies — jamais localStorage |

## Gestion des tokens — Stratégie retenue

**httpOnly cookies posés par Next.js** (BFF pattern léger).

Le browser ne touche jamais le token directement. Next.js reçoit le token de Laravel, le pose en cookie httpOnly, et l'injecte dans chaque requête API via `withCredentials`.

```
Browser              Next.js                      Laravel API
   |                         |                         |
   |-- POST /api/auth/login ->|                         |
   |                         |-- POST /auth/login ----->|
   |                         |<-- { access_token }      |
   |<- Set-Cookie:           |                         |
   |   sakan_token=...       |                         |
   |   (httpOnly, Secure)    |                         |
   |                         |                         |
   |-- appel API client ---->|                         |
   |   (axios withCredentials)|-- Authorization: Bearer->|
   |<-- données -------------|<-- données --------------|
```

### Cookie posé

| Cookie          | Contenu              | Options                                         |
| --------------- | -------------------- | ----------------------------------------------- |
| `sakan_token` | Opaque token Sanctum | httpOnly · Secure · SameSite=Strict · Path=/ |

> Sanctum gère les tokens avec une durée d'expiration configurable.
>
> Pas besoin de refresh token séparé pour le web — le token est révoqué au logout.
>
> Pour la **mobile app future** : les tokens seront émis et stockés côté app native (pas de cookie).

SAKAN a besoin de :

* Émettre des tokens pour ses propres clients (web + mobile)
* Consommer Google OAuth pour ses propres users (via Socialite — indépendant du choix Sanctum/Passport)

## Laravel — Routes API (`routes/api.php`)

### Auth

```php
Route::prefix('auth')->group(function () {
    // Email / password
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('logout',   [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me',        [AuthController::class, 'me'])->middleware('auth:sanctum');

    // Google OAuth
    Route::get('google/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('google/callback', [SocialAuthController::class, 'callback']);
});
```

### Properties

```php
Route::get('properties',      [PropertyController::class, 'index']);  // public
Route::get('properties/{id}', [PropertyController::class, 'show']);   // public

Route::middleware('auth:sanctum')->group(function () {
    Route::post('properties',        [PropertyController::class, 'store']);
    Route::patch('properties/{id}',  [PropertyController::class, 'update']);
    Route::delete('properties/{id}', [PropertyController::class, 'destroy']);
    Route::get('user/properties',    [PropertyController::class, 'myProperties']);
    Route::get('user/contacts',      [ContactController::class, 'myContacts']);
    Route::patch('user/me',          [UserController::class, 'update']);
    Route::get('upload/presign',     [UploadController::class, 'presign']);
});
```

### Admin

```php
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('properties',         [AdminPropertyController::class, 'index']);
    Route::patch('properties/{id}',  [AdminPropertyController::class, 'update']);
    Route::delete('properties/{id}', [AdminPropertyController::class, 'destroy']);
    Route::get('users',              [AdminUserController::class, 'index']);
    Route::patch('users/{id}',       [AdminUserController::class, 'update']);
});
```

---

## Laravel — AuthController (email / password)

```php
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
            'password' => bcrypt($data['password']),
        ]);

        return $this->issueTokenResponse($user);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!auth()->attempt($credentials)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        return $this->issueTokenResponse(auth()->user());
    }

    public function logout(Request $request): JsonResponse
    {
        // Révoquer uniquement le token courant
        $request->user()->currentAccessToken()->delete();

        return response()
            ->json(['message' => 'Déconnecté'])
            ->cookie(Cookie::forget('sakan_token'));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function issueTokenResponse(User $user): JsonResponse
    {
        // Révoquer les anciens tokens web (optionnel — évite l'accumulation)
        $user->tokens()->where('name', 'sakan-web')->delete();

        $token = $user->createToken('sakan-web')->plainTextToken;

        return response()
            ->json(['user' => $user])
            ->cookie(
                'sakan_token',          // nom
                $token,                  // valeur
                60 * 24 * 30,           // durée (minutes) — 30 jours
                '/',                    // path
                null,                   // domain
                true,                   // secure (HTTPS only)
                true,                   // httpOnly
                false,                  // raw
                'strict'                // sameSite
            );
    }
}
```

---

## Laravel — SocialAuthController (Google OAuth)

```php
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

        // Trouver ou créer l'utilisateur
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

        // Révoquer anciens tokens web + émettre un nouveau
        $user->tokens()->where('name', 'sakan-web')->delete();
        $token = $user->createToken('sakan-web')->plainTextToken;

        // Redirect vers le frontend avec le cookie posé
        return redirect(config('app.frontend_url') . '/?auth=success')
            ->cookie('sakan_token', $token, 60 * 24 * 30, '/', null, true, true, false, 'strict');
    }
}
```

### Flow Google OAuth complet

```
User clique "Continuer avec Google"
  → window.location = https://api.sakan.tn/api/auth/google/redirect
  → Socialite redirige vers accounts.google.com
  → User accepte
  → Google callback → GET /api/auth/google/callback
  → Laravel : crée/trouve le user, émet le token Sanctum, pose le cookie
  → Redirect vers https://sakan.tn/?auth=success
  → Next.js layout détecte ?auth=success → authApi.me() → user connecté
```

### `config/services.php`

```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('APP_URL') . '/api/auth/google/callback',
],
```

---

## Laravel — Middleware rôles

```php
// app/Http/Middleware/CheckRole.php
public function handle(Request $request, Closure $next, string $role): Response
{
    if ($request->user()?->role !== $role) {
        return response()->json(['message' => 'Accès refusé'], 403);
    }
    return $next($request);
}
```

Enregistré dans `bootstrap/app.php` :

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['role' => CheckRole::class]);
})
```

**Rôles disponibles :**

| Rôle           | Accès                                        |
| --------------- | --------------------------------------------- |
| `particulier` | Publier et gérer ses propres biens           |
| `agent`       | Idem + badge "Agent" visible sur ses annonces |
| `admin`       | Tout + panel `/admin`                       |

CORS (`config/cors.php`)

```php
'paths'                => ['api/*'],
'allowed_origins'      => ['https://sakan.tn', 'http://localhost:3000'],
'allowed_methods'      => ['*'],
'allowed_headers'      => ['*'],
'supports_credentials' => true, // indispensable pour les cookies cross-domain
```

---
