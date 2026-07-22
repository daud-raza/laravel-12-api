<?php

use Illuminate\Support\Facades\Route;
use Modules\TaskManager\Http\Controllers\Web\TaskPageController;

// Mapped with the "web" middleware group by the module RouteServiceProvider.
Route::middleware('auth')->group(function () {
    Route::get('/tasks', [TaskPageController::class, 'index'])->name('tasks.index');
});
