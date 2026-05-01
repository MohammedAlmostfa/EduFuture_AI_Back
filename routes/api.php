<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgetPasswordController;

// 🔓 Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/loginWithGoogle', [AuthController::class, 'loginWithGoogle']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/verify-email', [AuthController::class, 'verify']);
Route::post('/resendCode', [AuthController::class, 'resendCode']);

Route::post('/checkEmail', [ForgetPasswordController::class, 'checkEmail']);
Route::post('/checkCode', [ForgetPasswordController::class, 'checkCode']);
Route::post('/changePassword', [ForgetPasswordController::class, 'changePassword']);


// 🔐 Protected routes (JWT required)
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});
