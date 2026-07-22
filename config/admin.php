<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kredensial Admin
    |--------------------------------------------------------------------------
    |
    | WAJIB dibaca lewat config(), bukan env(), di kode aplikasi.
    |
    | Setelah `php artisan config:cache`, Laravel tidak lagi memuat file .env saat
    | runtime, sehingga env() di luar file config akan mengembalikan null — itu
    | yang dulu membuat seluruh penyimpanan admin gagal dengan 401.
    |
    | Sengaja TIDAK ada nilai fallback: kalau .env tidak terbaca, autentikasi harus
    | gagal tertutup (fail closed), bukan jatuh ke kredensial default yang bisa ditebak.
    |
    */

    'password' => env('ADMIN_PASSWORD'),

    'api_token' => env('ADMIN_API_TOKEN'),

];
