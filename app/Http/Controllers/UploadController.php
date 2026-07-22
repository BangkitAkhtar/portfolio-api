<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        // PHP membuang $_POST & $_FILES sepenuhnya kalau body melebihi post_max_size,
        // sehingga Laravel cuma melihat request kosong dan melapor "field required" —
        // membingungkan. Deteksi kondisi ini dulu supaya pesannya jelas & bisa ditindak.
        $contentLength = (int) ($request->server('CONTENT_LENGTH') ?? 0);
        $postMax = $this->iniToBytes((string) ini_get('post_max_size'));
        if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax && empty($_FILES)) {
            return response()->json([
                'message' => 'File terlalu besar untuk server (batas post_max_size = '
                    . ini_get('post_max_size') . '). Naikkan upload_max_filesize & post_max_size '
                    . 'di cPanel > MultiPHP INI Editor, atau kompres file dulu.',
            ], 413);
        }

        // Deteksi apakah frontend mengirim 'image' atau 'file'
        $uploadKey = $request->hasFile('image') ? 'image' : 'file';

        // Kalau PHP menolak karena melebihi upload_max_filesize, file tetap muncul di
        // $_FILES tapi dengan kode error INI_SIZE — tangani terpisah supaya jelas.
        $rawFile = $_FILES[$uploadKey] ?? null;
        if (is_array($rawFile) && ($rawFile['error'] ?? null) === UPLOAD_ERR_INI_SIZE) {
            return response()->json([
                'message' => 'File melebihi batas upload server (upload_max_filesize = '
                    . ini_get('upload_max_filesize') . '). Naikkan di cPanel > MultiPHP INI Editor, '
                    . 'atau kompres file dulu sebelum upload.',
            ], 413);
        }

        // Cek dulu ekstensi supaya bisa pakai aturan validasi berbeda untuk PDF
        // (aturan 'image' bawaan Laravel menolak PDF, dan PDF wajar berukuran lebih besar)
        $probe = $request->file($uploadKey);
        $isPdf = $probe && strtolower($probe->getClientOriginalExtension()) === 'pdf';

        // Jangan menjanjikan batas lebih besar dari yang sanggup diterima PHP —
        // kalau tidak, user dapat error membingungkan alih-alih pesan yang jelas.
        $serverLimitKb = (int) floor($this->iniToBytes((string) ini_get('upload_max_filesize')) / 1024);
        $pdfMaxKb = $serverLimitKb > 0 ? min(20480, $serverLimitKb) : 20480;
        $imgMaxKb = $serverLimitKb > 0 ? min(5120, $serverLimitKb) : 5120;

        $request->validate([
            $uploadKey => $isPdf
                ? "required|file|mimes:pdf|max:{$pdfMaxKb}"
                : "required|image|mimes:jpg,jpeg,png,webp,gif|max:{$imgMaxKb}",
        ]);

        $file = $request->file($uploadKey);
        $originalExtension = strtolower($file->getClientOriginalExtension());

        // Dapatkan nama file asli (tanpa ekstensi)
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // Tentukan ekstensi akhir (GIF & PDF tetap apa adanya, sisanya jadi WebP)
        $finalExtension = in_array($originalExtension, ['gif', 'pdf'], true)
            ? $originalExtension
            : 'webp';

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

        // ================= PDF =================
        if ($originalExtension === 'pdf') {
            // Simpan dulu file aslinya
            $file->storeAs('uploads', $filename, 'public');

            // Coba kompres pakai Ghostscript (kalau tersedia di server).
            // Kalau tidak ada, file tetap tersimpan apa adanya — upload tidak pernah gagal.
            $this->compressPdf($absolutePath);

            // Coba bikin thumbnail halaman pertama supaya preview di web ringan
            // (tidak perlu download PDF-nya). Konvensi nama: <file>.pdf.webp
            $this->makePdfThumbnail($absolutePath);

            return response()->json([
                'message' => 'PDF uploaded successfully',
                'url' => asset('storage/' . $path),
                'path' => $path,
            ]);
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

    /**
     * Ubah notasi ukuran php.ini ("2M", "8M", "512K") jadi jumlah byte.
     */
    private function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower($value[strlen($value) - 1]);
        $num = (int) $value;

        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $value,
        };
    }

    /**
     * Kompres PDF di tempat memakai Ghostscript, kalau tersedia.
     * Diam-diam dilewati kalau exec()/gs tidak ada — upload tidak boleh gagal karenanya.
     * Hasil hanya dipakai kalau benar-benar lebih kecil dari aslinya.
     */
    private function compressPdf(string $absolutePath): void
    {
        try {
            if (!is_file($absolutePath)) {
                return;
            }

            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (!function_exists('exec') || in_array('exec', $disabled, true)) {
                return;
            }

            // Cari binary Ghostscript
            $gs = null;
            foreach (['gs', '/usr/bin/gs', '/usr/local/bin/gs', '/opt/bin/gs'] as $candidate) {
                $out = [];
                $code = 1;
                @exec(escapeshellcmd($candidate) . ' --version 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out)) {
                    $gs = $candidate;
                    break;
                }
            }
            if (!$gs) {
                return;
            }

            $tmp = $absolutePath . '.compressed.pdf';

            // /ebook = target ~150dpi: seimbang antara ukuran kecil & teks/gambar tetap terbaca
            $cmd = escapeshellcmd($gs)
                . ' -sDEVICE=pdfwrite'
                . ' -dCompatibilityLevel=1.4'
                . ' -dPDFSETTINGS=/ebook'
                . ' -dNOPAUSE -dQUIET -dBATCH -dSAFER'
                . ' -sOutputFile=' . escapeshellarg($tmp)
                . ' ' . escapeshellarg($absolutePath)
                . ' 2>/dev/null';

            $out = [];
            $code = 1;
            @exec($cmd, $out, $code);

            if ($code === 0 && is_file($tmp) && filesize($tmp) > 0) {
                // Hanya pakai hasil kompresi kalau memang lebih kecil
                if (filesize($tmp) < filesize($absolutePath)) {
                    @rename($tmp, $absolutePath);
                } else {
                    @unlink($tmp);
                }
            } elseif (is_file($tmp)) {
                @unlink($tmp);
            }
        } catch (\Throwable $e) {
            // Sengaja diabaikan: kompresi itu bonus, bukan syarat upload berhasil
        }
    }

    /**
     * Bikin thumbnail WebP dari halaman pertama PDF (butuh Imagick + delegate PDF).
     * Disimpan sebagai "<file>.pdf.webp" supaya frontend bisa menebak namanya.
     * Dilewati diam-diam kalau server tidak mendukung.
     */
    private function makePdfThumbnail(string $absolutePath): void
    {
        try {
            if (!is_file($absolutePath) || !extension_loaded('imagick')) {
                return;
            }
            if (empty(\Imagick::queryFormats('PDF'))) {
                return;
            }

            $im = new \Imagick();
            $im->setResolution(150, 150);
            $im->readImage($absolutePath . '[0]'); // halaman pertama saja
            $im->setImageBackgroundColor('white');
            $im = $im->flattenImages();            // buang alpha supaya tidak hitam
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality(82);
            $im->thumbnailImage(800, 0);           // lebar maks 800px, rasio dijaga
            $im->writeImage($absolutePath . '.webp');
            $im->clear();
            $im->destroy();
        } catch (\Throwable $e) {
            // Sengaja diabaikan: preview akan jatuh ke viewer browser di sisi frontend
        }
    }
}
