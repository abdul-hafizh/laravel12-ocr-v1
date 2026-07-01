<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WablasWebhookController;
use App\Http\Controllers\Api\ImageScanController;
use App\Http\Controllers\Api\TelegramWebhookController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/wablas/webhook', [WablasWebhookController::class, 'handle']);
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/image-scans', [ImageScanController::class, 'store']);
    Route::get('/image-scans', [ImageScanController::class, 'index']);
    Route::get('/image-scans/{id}', [ImageScanController::class, 'show']);
});