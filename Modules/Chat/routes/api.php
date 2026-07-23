<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\Api\ChatUserController;
use Modules\Chat\Http\Controllers\Api\ConversationController;
use Modules\Chat\Http\Controllers\Api\MessageController;

// Mapped with the "api" prefix + middleware by the module RouteServiceProvider.
Route::middleware('auth:sanctum')->group(function () {
    // User search (declare before wildcards)
    Route::get('/users', [ChatUserController::class, 'index']);

    // Conversations
    Route::get('/conversations',                       [ConversationController::class, 'index']);
    Route::post('/conversations',                      [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}',        [ConversationController::class, 'show']);
    Route::delete('/conversations/{conversation}',     [ConversationController::class, 'destroy']);
    Route::post('/conversations/{conversation}/read',  [ConversationController::class, 'read']);

    // Messages
    Route::get('/conversations/{conversation}/messages',  [MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:chat-send');
});
