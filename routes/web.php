<?php

use App\Http\Controllers;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [Controllers\HomeController::class,'index'])->name('dashboard');
});

Route::get('/u/{user:username}', [Controllers\ProfileController::class, 'index'])->name('profile');

Route::get('/g/{group:slug}', [Controllers\GroupController::class, 'profile'])->name('group.profile');

Route::get('/group/approve-invitation/{token}', [Controllers\GroupController::class, 'approveInvitation'])->name('group.approveInvitation');

Route::middleware('auth')->group(function () {
    Route::post('/user/follow/{user}', [Controllers\UserController::class, 'follow'])->name('user.follow');

    Route::prefix('/profile')->group(function () {
        Route::post('/update-images', [Controllers\ProfileController::class, 'updateImage'])->name('profile.updateImages');
        Route::patch('/', [Controllers\ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/', [Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::prefix('/group')->group(function () {
        Route::post('/', [Controllers\GroupController::class, 'store'])->name('group.create');
        Route::put('/{group:slug}', [Controllers\GroupController::class, 'update'])->name('group.update');
        Route::post('/update-images/{group:slug}', [Controllers\GroupController::class, 'updateImage'])->name('group.updateImages');
        Route::post('/invite/{group:slug}', [Controllers\GroupController::class, 'inviteUsers'])->name('group.inviteUsers');
        Route::post('/join/{group:slug}', [Controllers\GroupController::class, 'join'])->name('group.join');
        Route::post('/approve-request/{group:slug}', [Controllers\GroupController::class, 'approveRequest'])->name('group.approveRequest');
        Route::post('/change-role/{group:slug}', [Controllers\GroupController::class, 'changeRole'])->name('group.changeRole');
        Route::delete('/remove-user/{group:slug}', [Controllers\GroupController::class, 'removeUser'])->name('group.removeUser');
    });

    Route::prefix('/post')->group(function () {
        Route::get('/{post}', [Controllers\PostController::class, 'view'])->name('post.view');
        Route::post('', [Controllers\PostController::class, 'store'])->name('post.create');
        Route::post('/ai-post', [Controllers\PostController::class, 'generateContentWithOpenAI'])->name('post.aiContent');
        Route::put('/{post}', [Controllers\PostController::class, 'update'])->name('post.update');
        Route::delete('/{post}', [Controllers\PostController::class, 'destroy'])->name('post.destroy');
        Route::get('/download/{attachment}', [Controllers\PostController::class, 'downloadAttachment']) ->name('post.download');
        Route::post('/{post}/reaction', [Controllers\PostController::class, 'postReaction'])->name('post.reaction');
        Route::post('/{post}/comment', [Controllers\PostController::class, 'createComment'])->name('comment.create');
    });

    Route::prefix('/comment')->group(function () {
        Route::delete('/{comment}', [Controllers\PostController::class, 'deleteComment'])->name('comment.delete');
        Route::put('/{comment}', [Controllers\PostController::class, 'updateComment'])->name('comment.update');
        Route::post('/{comment}/reaction', [Controllers\PostController::class, 'commentReaction'])->name('comment.reaction');
    });

    Route::get('/search/{search?}', [Controllers\SearchController::class, 'search'])->name('search');
});

require __DIR__.'/auth.php';
