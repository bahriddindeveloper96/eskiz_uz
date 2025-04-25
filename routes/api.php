<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-sms', [AuthController::class, 'verifySms']);
Route::post('/resend-sms', [AuthController::class, 'resendSms']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
