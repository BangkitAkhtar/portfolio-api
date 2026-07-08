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

        $validPassword = env('ADMIN_PASSWORD', 'singa123'); // fallback if env is missing
        $validToken = env('ADMIN_API_TOKEN');

        if ($request->password === $validPassword) {
            // Give a safe fallback if token is somehow missing in env during development
            $tokenToReturn = $validToken ?: 'rahasia_token_panjang_sekali_123';
            return response()->json([
                'success' => true,
                'token' => $tokenToReturn
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Invalid password'
        ], 401);
    }
}
