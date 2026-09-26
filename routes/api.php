<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\CategoryController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/verify-email/resend', [AuthController::class, 'verifyEmailResendCode']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/reset-password/resend', [AuthController::class, 'passwordResetCodeResend']);
Route::get('/recipes', [RecipeController::class, 'index']);
Route::get('/recipes/search', [RecipeController::class, 'search']);
Route::get('/recipes/{recipe}', [RecipeController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{recipe}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{recipe}', [FavoriteController::class, 'destroy']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
});
