<?php

use App\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['apikey', 'throttle:api'])->group(function () {
    Route::get('/products', [V1Controller::class, 'products']);
    Route::get('/wallet', [V1Controller::class, 'wallet']);
    Route::post('/verify/{product}', [V1Controller::class, 'verify']);
    Route::get('/verifications/{vid}', [V1Controller::class, 'show']);
    Route::post('/verifications/{vid}/refresh', [V1Controller::class, 'refresh']);
    Route::post('/verifications/{vid}/otp', [V1Controller::class, 'otp']);
    Route::get('/verifications/{vid}/documents/{type}', [V1Controller::class, 'document']);
});
