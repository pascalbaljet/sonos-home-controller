<?php

use App\Http\Controllers\SonosController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::prefix('api/sonos')->group(function () {
    // Get all available rooms
    Route::get('/rooms', [SonosController::class, 'getRooms']);

    // Play stream on a room
    Route::post('/playStreamOnRoom', [SonosController::class, 'playStreamOnRoom']);

    // Stop playback
    Route::post('/stop', [SonosController::class, 'stop']);

    // Volume controls
    Route::post('/volumeUp', [SonosController::class, 'volumeUp']);
    Route::post('/volumeDown', [SonosController::class, 'volumeDown']);
    Route::get('/volume', [SonosController::class, 'getVolume']);
});
