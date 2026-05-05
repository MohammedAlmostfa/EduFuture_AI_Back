<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgetPasswordController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LectureAnalysisController;

// 🔓 Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/loginWithGoogle', [AuthController::class, 'loginWithGoogle']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/verify-email', [AuthController::class, 'verify']);
Route::post('/resendCode', [AuthController::class, 'resendCode']);

Route::post('/checkEmail', [ForgetPasswordController::class, 'checkEmail']);
Route::post('/checkCode', [ForgetPasswordController::class, 'checkCode']);
Route::post('/changePassword', [ForgetPasswordController::class, 'changePassword']);


// 📁 File routes (protected)
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);




});
Route::post('/analyze-lecture', [LectureAnalysisController::class, 'uploadAndAnalyze']);
