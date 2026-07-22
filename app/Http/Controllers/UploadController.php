<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        // Deteksi apakah frontend mengirim 'image' atau 'file'
        $uploadKey = $request->hasFile('image') ? 'image' : 'file';

        // Validasi input
        $request->validate([
            $uploadKey => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
        ]);

        $file = $request->file($uploadKey);
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

        // Jika file adalah GIF, ATAU server tidak support WebP (GD Library missing WebP), langsung simpan file aslinya
        if ($finalExtension === 'gif' || !function_exists('imagewebp') || !function_exists('imagecreatefromjpeg')) {
            // Gunakan file asli dan ekstensi aslinya
            $filename = $safeName . '.' . $originalExtension;
            $counter = 1;
            while (Storage::disk('public')->exists('uploads/' . $filename)) {
                $filename = $safeName . ' (' . $counter . ').' . $originalExtension;
                $counter++;
            }
            $path = 'uploads/' . $filename;
            $file->storeAs('uploads', $filename, 'public');
        } else {
          try {
                $sourcePath = $file->getRealPath();
                $image = false;

                // --- FITUR BARU: SMART COMPRESSION ---
                $fileSize = $file->getSize(); // Dapatkan ukuran file asli dalam Bytes
                $quality = 85; // Kualitas default (Titik aman)

                if ($fileSize <= 100000) {
                    $quality = 70;
                } elseif ($fileSize <= 500000) {
                    $quality = 80;
                } else {
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
                    // GD kadang gagal decode PNG tertentu (16-bit/Photoshop, ICC profile aneh, dll)
                    // walau file-nya valid. Jangan block upload — fallback simpan file aslinya,
                    // sama seperti pola fallback GIF/error tak terduga di bawah.
                    throw new \RuntimeException('GD gagal decode gambar, fallback ke file asli.');
                }

                // --- SMART RESIZING (Maks 800px) ---
                // Dikembalikan ke 800px: gambar juga tampil besar di Lightbox/DetailDialog
                // (bisa sampai 85vh di layar desktop), jadi 500px bikin gambar pecah/buram
                // saat di-zoom/lightbox.
                $width = imagesx($image);
                $height = imagesy($image);
                $maxWidth = 800;
                $maxHeight = 800;

                if ($width > $maxWidth || $height > $maxHeight) {
                    $ratio = min($maxWidth / $width, $maxHeight / $height);
                    $newWidth = round($width * $ratio);
                    $newHeight = round($height * $ratio);
                    
                    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                    // Handle transparansi
                    imagealphablending($resizedImage, false);
                    imagesavealpha($resizedImage, true);
                    $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                    imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
                    
                    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($image);
                    $image = $resizedImage; // Gunakan gambar yang sudah di-resize
                }
                // ---------------------------------------------

                // Convert dan simpan ke format WebP dengan kualitas yang sudah diatur otomatis
                $success = imagewebp($image, $absolutePath, $quality);

                // Bersihkan memory server
                imagedestroy($image);

                if (!$success) {
                    throw new \RuntimeException('Gagal menulis WebP, fallback ke file asli.');
                }

            } catch (\Throwable $e) { // Catch \Throwable untuk menangkap Fatal Error (Error) juga
                // Jika terjadi fatal error saat convert, fallback ke upload biasa
                $filename = $safeName . '.' . $originalExtension;
                $counter = 1;
                while (Storage::disk('public')->exists('uploads/' . $filename)) {
                    $filename = $safeName . ' (' . $counter . ').' . $originalExtension;
                    $counter++;
                }
                $path = 'uploads/' . $filename;
                $file->storeAs('uploads', $filename, 'public');
            }
        }

        return response()->json([
            'message' => 'File uploaded and converted successfully',
            'url' => asset('storage/' . $path),
            'path' => $path,
        ]);
    }
}
