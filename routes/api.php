<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DefaultMessageController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::middleware('throttle:6,1')->group(function () {
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
});

Route::middleware('auth:sanctum')->group(function () {
    // Account
    Route::get('current-user', [AuthController::class, 'me'])->name('current-user');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::put('change-password', [AuthController::class, 'changePassword'])->name('change-password');
    Route::post('initiateEmailChange', [AuthController::class, 'initiateEmailChange'])->name('initiateEmailChange');
    Route::post('confirmEmailChange', [AuthController::class, 'confirmEmailChange'])->name('confirmEmailChange');
    Route::post('initiatePhoneChange', [AuthController::class, 'initiatePhoneChange'])->name('initiatePhoneChange');
    Route::post('confirmPhoneChange', [AuthController::class, 'confirmPhoneChange'])->name('confirmPhoneChange');

    // Conversations and their messages
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('conversations/{conversation}/read', [ConversationController::class, 'markAsRead'])->name('conversations.read');
    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])->name('conversations.messages.index');
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->name('conversations.messages.store');

    // Predefined messages: everyone signed in can read them, staff manage them
    Route::apiResource('default-messages', DefaultMessageController::class)->only(['index', 'show']);

    Route::middleware('staff')->group(function () {
        Route::apiResource('default-messages', DefaultMessageController::class)->only(['store', 'update', 'destroy']);
        Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
        Route::apiResource('users', UserController::class);
    });
});
