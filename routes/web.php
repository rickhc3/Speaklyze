<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('videos', function () {
        return Inertia::render('VideoView', [
            'userId' => auth()->id(),
        ]);
    })->name('videos');

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::prefix('api')->group(function () {
        Route::resource('/videos', VideoController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('/videos/{id}/retry', [VideoController::class, 'retry']);
        Route::post('/videos/{id}/restore', [VideoController::class, 'restore']);
        Route::delete('/videos/{id}/force-delete', [VideoController::class, 'forceDelete']);
        Route::delete('/videos/trash/empty', [VideoController::class, 'emptyTrash']);


        Route::get('/chat/{video}', [ChatController::class, 'getMessages']);
        Route::post('/chat/{video}', [ChatController::class, 'ask']);
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
