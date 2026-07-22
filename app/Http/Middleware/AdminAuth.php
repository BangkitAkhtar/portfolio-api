<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-Admin-Token');

        // config(), BUKAN env(): setelah `php artisan config:cache` Laravel tidak lagi
        // memuat .env saat runtime, sehingga env() di sini akan bernilai null dan
        // membuat semua permintaan admin ditolak 401.
        $validToken = config('admin.api_token');

        // hash_equals: perbandingan waktu-konstan, supaya token tidak bisa ditebak
        // sedikit demi sedikit lewat pengukuran waktu respons.
        if (!$token || !$validToken || !hash_equals((string) $validToken, (string) $token)) {
            return response()->json(['error' => 'Unauthorized. API Token is missing or invalid.'], 401);
        }

        return $next($request);
    }
}
