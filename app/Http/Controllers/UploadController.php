<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        // Validasi input
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        $file = $request->file('file');
        $originalExtension = strtolower($file->getClientOriginalExtension());

        // Dapatkan nama file asli (tanpa ekstensi)
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // Tentukan ekstensi akhir (GIF tetap GIF, sisanya jadi WebP)
        $finalExtension = ($originalExtension === 'gif') ? 'gif' : 'webp';

        // BERSILAN NAMA FILE: Hapus karakter aneh yang bisa merusak link URL
        // Hanya izinkan huruf, angka, spasi, strip (-), dan underscore (_)
        $safeName = preg_replace('/[^A-Za-z0-9\-_\s]/', '', $originalName);
        if (empty(trim($safeName))) {
            $safeName = 'image'; // Jaga-jaga jika nama aslinya berisi simbol semua
        }

        // Siapkan nama file
        $filename = $safeName . '.' . $finalExtension;
        $counter = 1;

        // LOGIKA NAMA KEMBAR: Cek apakah nama file sudah ada di dalam storage public
        while (Storage::disk('public')->exists('uploads/' . $filename)) {
            // Jika ada, tambahkan (1), (2), dst sebelum titik ekstensi
            $filename = $safeName . ' (' . $counter . ').' . $finalExtension;
            $counter++;
        }

        $path = 'uploads/' . $filename;
        $absolutePath = storage_path('app/public/' . $path);

        // Pastikan folder uploads sudah ada
        if (!file_exists(storage_path('app/public/uploads'))) {
            mkdir(storage_path('app/public/uploads'), 0755, true);
        }

        // Jika file adalah GIF, langsung pindahkan dengan nama aslinya (tidak diconvert)
        if ($finalExtension === 'gif') {
            // Kita pakai storeAs agar namanya sesuai dengan yang kita buat (bukan random hash)
            $request->file('file')->storeAs('uploads', $filename, 'public');
        } else {
          try {
                $sourcePath = $file->getRealPath();
                $image = false;

                // --- FITUR BARU: SMART COMPRESSION ---
                $fileSize = $file->getSize(); // Dapatkan ukuran file asli dalam Bytes
                $quality = 85; // Kualitas default (Titik aman)

                if ($fileSize <= 100000) {
                    // Jika file asli SANGAT KECIL (Di bawah 100 KB)
                    // Gunakan kualitas 70 karena file aslinya sudah burik/terkompres
                    $quality = 70;
                } elseif ($fileSize <= 500000) {
                    // Jika file asli KECIL (100 KB - 500 KB)
                    $quality = 80;
                } else {
                    // Jika file asli BESAR (Di atas 500 KB / dari kamera resolusi tinggi)
                    $quality = 85;
                }
                // -------------------------------------

                // Baca gambar menggunakan fungsi Bawaan PHP (Native GD)
                if ($originalExtension === 'jpg' || $originalExtension === 'jpeg') {
                    $image = @imagecreatefromjpeg($sourcePath);
                } elseif ($originalExtension === 'png') {
                    $image = @imagecreatefrompng($sourcePath);
                    if ($image) {
                        // Handle transparansi background PNG
                        imagepalettetotruecolor($image);
                        imagealphablending($image, true);
                        imagesavealpha($image, true);
                    }
                } elseif ($originalExtension === 'webp') {
                    $image = @imagecreatefromwebp($sourcePath);
                }

                if (!$image) {
                    return response()->json([
                        'message' => 'Server gagal membaca format gambar ini.'
                    ], 500);
                }

                // Convert dan simpan ke format WebP dengan kualitas yang sudah diatur otomatis
                $success = imagewebp($image, $absolutePath, $quality);

                // Bersihkan memory server
                imagedestroy($image);

                if (!$success) {
                    return response()->json([
                        'message' => 'Gagal menulis file WebP ke dalam storage cPanel.'
                    ], 500);
                }

            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'message' => 'File uploaded and converted successfully',
            'url' => asset('storage/' . $path),
            'path' => $path,
        ]);
    }
}
