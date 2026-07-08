<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\AdminAuth;

Route::get('/portfolio', [PortfolioController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware([AdminAuth::class])->group(function () {
    Route::post('/portfolio', [PortfolioController::class, 'store']);
    Route::post('/upload', [UploadController::class, 'upload']);
});
