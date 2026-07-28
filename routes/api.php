<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\AdminAuth;

Route::get('/portfolio', [PortfolioController::class, 'index']);

// Pembatasan percobaan login DI SERVER: 5 percobaan per menit per IP.
// Pembatasan di frontend memakai sessionStorage sehingga gampang dilewati dengan
// memanggil endpoint ini langsung — tanpa throttle ini, password admin bisa
// ditebak berulang tanpa batas.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware([AdminAuth::class])->group(function () {
    Route::post('/portfolio', [PortfolioController::class, 'store']);
    Route::post('/upload', [UploadController::class, 'upload']);
});
