<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AivvaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DirectionController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\WorldController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['ok' => true, 'name' => 'AIVVA']);

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/aivvas', [AivvaController::class, 'index']);
    Route::post('/aivvas', [AivvaController::class, 'store']);
    Route::get('/aivvas/{aivva}', [AivvaController::class, 'show']);
    Route::post('/aivvas/{aivva}/activate', [AivvaController::class, 'activate']);
    Route::post('/aivvas/{aivva}/pause', [AivvaController::class, 'pause']);
    Route::post('/aivvas/{aivva}/recall', [AivvaController::class, 'recall']);
    Route::post('/aivvas/{aivva}/stop-spending', [AivvaController::class, 'stopSpending']);
    Route::post('/aivvas/{aivva}/cancel-goal', [AivvaController::class, 'cancelGoal']);
    Route::patch('/aivvas/{aivva}/permissions', [AivvaController::class, 'permissions']);
    Route::post('/aivvas/{aivva}/tick', [AivvaController::class, 'tick']);
    Route::get('/aivvas/{aivva}/live', [AivvaController::class, 'live']);

    Route::get('/aivvas/{aivva}/chat', [ChatController::class, 'index']);
    Route::post('/aivvas/{aivva}/chat', [ChatController::class, 'store']);

    Route::post('/aivvas/{aivva}/direction', [DirectionController::class, 'interpret']);
    Route::post('/aivvas/{aivva}/direction/confirm', [DirectionController::class, 'confirm']);

    Route::get('/aivvas/{aivva}/activity', [WorldController::class, 'activity']);
    Route::get('/aivvas/{aivva}/memories', [WorldController::class, 'memories']);
    Route::get('/aivvas/{aivva}/messages', [WorldController::class, 'messages']);
    Route::get('/aivvas/{aivva}/conversations', [WorldController::class, 'conversations']);
    Route::get('/aivvas/{aivva}/relationships', [WorldController::class, 'relationships']);
    Route::get('/aivvas/{aivva}/works', [WorldController::class, 'works']);
    Route::get('/aivvas/{aivva}/wallet', [WorldController::class, 'wallet']);

    Route::get('/world/map', [WorldController::class, 'map']);
    Route::get('/world/locations', [WorldController::class, 'locations']);
    Route::get('/marketplace', [WorldController::class, 'marketplace']);
    Route::post('/aivvas/{aivva}/marketplace/requests', [MarketplaceController::class, 'storeRequest']);
    Route::post('/aivvas/{aivva}/marketplace/listings', [MarketplaceController::class, 'storeListing']);
    Route::get('/notifications', [WorldController::class, 'notifications']);
    Route::post('/world/tick', [WorldController::class, 'tickWorld']);

    Route::get('/admin/health', [AdminController::class, 'health']);
});
