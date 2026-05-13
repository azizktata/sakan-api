<?php

use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminPropertyController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AmenityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EstimationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Reference data (public)
Route::get('locations', [LocationController::class, 'index']);
Route::get('amenities', [AmenityController::class, 'index']);

// AI Estimation (public)
Route::post('estimate', [EstimationController::class, 'estimate']);
Route::post('estimate/{estimation_id}/feedback', [EstimationController::class, 'feedback']);

// Analytics — view tracking (public, rate-limited)
Route::post('events/view', [AnalyticsController::class, 'trackView'])->middleware('throttle:30,1');
Route::patch('events/view/{view_id}/duration', [AnalyticsController::class, 'updateDuration'])->middleware('throttle:60,1');
Route::post('events/search', [AnalyticsController::class, 'trackSearch'])->middleware('throttle:60,1');

// Session tracking (public)
Route::post('sessions/start', [SessionController::class, 'start'])->middleware('throttle:60,1');
Route::patch('sessions/ping',  [SessionController::class, 'ping'])->middleware('throttle:120,1');
Route::patch('sessions/end',   [SessionController::class, 'end'])->middleware('throttle:60,1');

// Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('logout',   [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me',        [AuthController::class, 'me'])->middleware('auth:sanctum');

    Route::get('google/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('google/callback', [SocialAuthController::class, 'callback']);
});

// Properties (public)
Route::get('properties',       [PropertyController::class, 'index']);
Route::get('properties/{id}',  [PropertyController::class, 'show']);

// Contacts (public — guest or logged-in)
Route::post('properties/{id}/contacts', [ContactController::class, 'store']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::post('properties',         [PropertyController::class, 'store']);
    Route::patch('properties/{id}',   [PropertyController::class, 'update']);
    Route::delete('properties/{id}',  [PropertyController::class, 'destroy']);

    Route::get('user/properties', [PropertyController::class, 'myProperties']);
    Route::get('user/contacts',   [ContactController::class, 'myContacts']);
    Route::patch('user/contacts/{id}/read', [ContactController::class, 'markRead']);
    Route::patch('user/me',       [UserController::class, 'update']);

    Route::post('upload/image', [UploadController::class, 'upload']);

    // Analytics — owner KPIs
    Route::get('analytics/my-properties',          [AnalyticsController::class, 'ownerSummary']);
    Route::get('analytics/property/{id}',          [AnalyticsController::class, 'propertyStats']);
    Route::get('analytics/property/{id}/trend',    [AnalyticsController::class, 'propertyTrend']);
});

// Admin
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('properties',          [AdminPropertyController::class, 'index']);
    Route::patch('properties/{id}',   [AdminPropertyController::class, 'update']);
    Route::delete('properties/{id}',  [AdminPropertyController::class, 'destroy']);

    Route::get('users',               [AdminUserController::class, 'index']);
    Route::patch('users/{id}',        [AdminUserController::class, 'update']);

    // Analytics — admin KPIs
    Route::get('analytics/overview',           [AdminAnalyticsController::class, 'overview']);
    Route::get('analytics/top-properties',     [AdminAnalyticsController::class, 'topProperties']);
    Route::get('analytics/top-cities',         [AdminAnalyticsController::class, 'topCities']);
    Route::get('analytics/funnel',             [AdminAnalyticsController::class, 'conversionFunnel']);
    Route::get('analytics/estimation-dataset', [AdminAnalyticsController::class, 'estimationDataset']);
    Route::get('analytics/market-insights',    [AdminAnalyticsController::class, 'marketInsights']);
    Route::get('analytics/search-trends',      [AdminAnalyticsController::class, 'searchTrends']);
    Route::get('analytics/sessions',           [AdminAnalyticsController::class, 'sessionStats']);
    Route::get('analytics/geo-breakdown',      [AdminAnalyticsController::class, 'geoBreakdown']);
});
