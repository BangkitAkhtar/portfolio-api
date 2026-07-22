<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        // config(), BUKAN env(): setelah `php artisan config:cache`, env() di luar
        // file config mengembalikan null dan login akan selalu gagal.
        $validPassword = config('admin.password');
        $validToken = config('admin.api_token');

        // Fail closed. Sebelumnya ada fallback kredensial yang di-hardcode di sini —
        // berbahaya karena nilainya ikut tersimpan di riwayat repositori, sehingga
        // siapa pun yang bisa membaca kode bisa masuk kalau .env gagal terbaca.
        if (!$validPassword || !$validToken) {
            return response()->json([
                'success' => false,
                'error' => 'Admin credentials are not configured on the server.',
            ], 500);
        }

        if (hash_equals((string) $validPassword, (string) $request->password)) {
            return response()->json([
                'success' => true,
                'token' => $validToken,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Invalid password'
        ], 401);
    }
}
