<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioSetting extends Model
{
    // Pastikan kolom data diizinkan untuk diisi
    protected $fillable = ['data'];

    // WAJIB ADA: Beri tahu Laravel untuk mengubah JSON menjadi Array secara otomatis
    protected $casts = [
        'data' => 'array',
    ];
}
