<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\Web\ChatPageController;

// Mapped with the "web" middleware group by the module RouteServiceProvider.
Route::middleware('auth')->group(function () {
    Route::get('/chat', [ChatPageController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [ChatPageController::class, 'show'])->name('chat.show');
});
