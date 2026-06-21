<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WablasWebhookController;
use App\Http\Controllers\Api\ImageScanController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test-wa', function () {

    $result = \App\Libraries\SendSms::sendMessageWA(
        '6281314031553',
        'Halo test WA dari Laravel'
    );

    return response()->json($result);
});

Route::post('/wablas/webhook', [WablasWebhookController::class, 'handle']);
Route::post('/image-scans', [ImageScanController::class, 'store']);
Route::get('/image-scans', [ImageScanController::class, 'index']);
Route::get('/image-scans/{id}', [ImageScanController::class, 'show']);

