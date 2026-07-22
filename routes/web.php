<?php

use App\Http\Controllers\Web\Auth\WebAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Minimal session auth for the web chat UI.
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login']);
});
Route::post('/logout', [WebAuthController::class, 'logout'])->middleware('auth')->name('logout');

// Chat page routes live in the Chat module (Modules/Chat/routes/web.php).
