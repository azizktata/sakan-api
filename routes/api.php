<?php

use App\Http\Controllers\Admin\AdminPropertyController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AmenityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Reference data (public)
Route::get('locations', [LocationController::class, 'index']);
Route::get('amenities', [AmenityController::class, 'index']);

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
});

// Admin
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('properties',          [AdminPropertyController::class, 'index']);
    Route::patch('properties/{id}',   [AdminPropertyController::class, 'update']);
    Route::delete('properties/{id}',  [AdminPropertyController::class, 'destroy']);

    Route::get('users',               [AdminUserController::class, 'index']);
    Route::patch('users/{id}',        [AdminUserController::class, 'update']);
});
