<?php

use App\Http\Controllers\VideoController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
  Route::resource('/videos', VideoController::class)->only(['index', 'store', 'show', 'destroy']);
  Route::post('/videos/{id}/restore', [VideoController::class, 'restore']);


  Route::post('/chat/{video}', [ChatController::class, 'ask']);
  Route::get('/chat/{video}', [ChatController::class, 'getMessages']); // 🔥 Nova rota para buscar mensagens

});
