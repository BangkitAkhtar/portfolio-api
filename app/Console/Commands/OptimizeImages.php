<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OptimizeImages extends Command
{
    /**
     * Contoh pakai:
     *   php artisan images:optimize            (max 500px, kualitas 80, tulis di tempat)
     *   php artisan images:optimize --max=600  (ubah batas dimensi)
     *   php artisan images:optimize --dry-run  (cuma lihat perkiraan, tidak mengubah file)
     */
    protected $signature = 'images:optimize
                            {--max=500 : Dimensi maksimum (px) sisi terpanjang}
                            {--quality=80 : Kualitas WebP/JPEG (1-100)}
                            {--dry-run : Hanya laporkan, jangan tulis ulang file}';

    protected $description = 'Resize + kompres ulang gambar lama di storage/uploads agar tidak kegedean (menghemat bandwidth & mempercepat load)';

    public function handle(): int
    {
        $dir = storage_path('app/public/uploads');
        if (!is_dir($dir)) {
            $this->error("Folder tidak ditemukan: $dir");
            return self::FAILURE;
        }

        $max     = (int) $this->option('max');
        $quality = (int) $this->option('quality');
        $dry     = (bool) $this->option('dry-run');

        if (!function_exists('imagewebp')) {
            $this->error('Ekstensi GD dengan dukungan WebP tidak aktif di server ini.');
            return self::FAILURE;
        }

        $files = glob($dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
        if (empty($files)) {
            $this->warn('Tidak ada gambar (.jpg/.jpeg/.png/.webp) di folder uploads.');
            return self::SUCCESS;
        }

        $this->info(sprintf('%s %d gambar (maks %dpx, kualitas %d)%s',
            $dry ? 'Menganalisis' : 'Mengoptimasi', count($files), $max, $quality,
            $dry ? ' — MODE DRY RUN, tidak ada file yang diubah' : ''));

        $before = 0; $after = 0; $skipped = 0; $changed = 0;

        foreach ($files as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $origSize = filesize($path);
            $before += $origSize;

            $img = match ($ext) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($path),
                'png'         => @imagecreatefrompng($path),
                'webp'        => @imagecreatefromwebp($path),
                default       => false,
            };
            if (!$img) { $this->line("  ! lewati (gagal baca): " . basename($path)); $skipped++; $after += $origSize; continue; }

            $w = imagesx($img); $h = imagesy($img);

            // Hanya proses yang benar-benar kegedean
            if ($w <= $max && $h <= $max) {
                imagedestroy($img);
                $skipped++; $after += $origSize;
                continue;
            }

            $ratio = min($max / $w, $max / $h);
            $nw = (int) round($w * $ratio);
            $nh = (int) round($h * $ratio);

            $resized = imagecreatetruecolor($nw, $nh);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $nw, $nh, $transparent);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);

            if ($dry) {
                // perkiraan: encode ke buffer sementara untuk ukur ukuran baru
                ob_start();
                imagewebp($resized, null, $quality);
                $newBytes = ob_get_clean();
                $after += strlen($newBytes);
            } else {
                // tulis ulang dengan FORMAT ASLI supaya URL & ekstensi tetap valid
                match ($ext) {
                    'png'         => imagepng($resized, $path, 8),
                    'jpg', 'jpeg' => imagejpeg($resized, $path, $quality),
                    'webp'        => imagewebp($resized, $path, $quality),
                };
                clearstatcache(true, $path);
                $after += filesize($path);
            }
            imagedestroy($resized);

            $changed++;
            $this->line(sprintf('  %s %s  %dx%d → %dx%d', $dry ? '~' : '✓', basename($path), $w, $h, $nw, $nh));
        }

        $saved = $before - $after;
        $this->newLine();
        $this->info(sprintf('Selesai. Diubah: %d, dilewati (sudah kecil): %d', $changed, $skipped));
        $this->info(sprintf('Ukuran: %.1f KB → %.1f KB  (hemat %.1f KB / %.0f%%)',
            $before / 1024, $after / 1024, $saved / 1024, $before ? ($saved / $before * 100) : 0));

        return self::SUCCESS;
    }
}
