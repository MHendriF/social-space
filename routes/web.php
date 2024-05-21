<?php

use App\Http\Controllers;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [Controllers\HomeController::class,'index'])->name('dashboard');
});

Route::get('/u/{user:username}', [Controllers\ProfileController::class, 'index'])
    ->name('profile');

Route::middleware('auth')->group(function () {
    Route::post('/profile/update-images', [Controllers\ProfileController::class, 'updateImage'])
        ->name('profile.updateImages');
        Route::patch('/profile', [Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::post('/post', [Controllers\PostController::class, 'store'])
        ->name('post.create');
});

require __DIR__.'/auth.php';
