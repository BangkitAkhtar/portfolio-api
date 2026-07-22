<?php

namespace App\Console\Commands;

use App\Support\PdfProcessor;
use Illuminate\Console\Command;

class PdfThumbnails extends Command
{
    protected $signature = 'pdf:thumbnails
                            {--force : Buat ulang walau thumbnail sudah ada}
                            {--compress : Sekalian kompres PDF-nya juga (butuh Ghostscript)}';

    protected $description = 'Buat thumbnail untuk PDF yang sudah terlanjur diupload (sebelum Imagick aktif)';

    public function handle(): int
    {
        $dir = storage_path('app/public/uploads');
        if (!is_dir($dir)) {
            $this->error("Folder tidak ditemukan: $dir");

            return self::FAILURE;
        }

        if (!PdfProcessor::canThumbnail()) {
            $this->error('Imagick (dengan dukungan PDF) tidak tersedia di server ini.');
            $this->line('Aktifkan dulu di cPanel > Select PHP Version > Extensions > imagick,');
            $this->line('lalu cek dengan: php artisan pdf:check');

            return self::FAILURE;
        }

        $files = glob($dir . '/*.pdf') ?: [];
        if (empty($files)) {
            $this->warn('Tidak ada file PDF di folder uploads.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $compress = (bool) $this->option('compress');

        $this->info(sprintf('Memproses %d PDF...', count($files)));
        $this->newLine();

        $made = 0;
        $skipped = 0;
        $failed = 0;
        $savedBytes = 0;

        foreach ($files as $path) {
            $name = basename($path);
            $thumb = $path . '.webp';

            if (!$force && is_file($thumb)) {
                $this->line("  <fg=gray>lewati</> $name (thumbnail sudah ada)");
                $skipped++;
                continue;
            }

            if ($compress) {
                $before = filesize($path);
                if (PdfProcessor::compress($path)) {
                    clearstatcache(true, $path);
                    $savedBytes += $before - filesize($path);
                }
            }

            if (PdfProcessor::thumbnail($path)) {
                clearstatcache(true, $thumb);
                $this->line(sprintf('  <fg=green>OK</>     %s  (thumbnail %.0f KB)', $name, filesize($thumb) / 1024));
                $made++;
            } else {
                $this->line("  <fg=red>GAGAL</>  $name");
                $failed++;
            }
        }

        $this->newLine();
        $this->info(sprintf('Selesai. Dibuat: %d, dilewati: %d, gagal: %d', $made, $skipped, $failed));
        if ($compress && $savedBytes > 0) {
            $this->info(sprintf('Kompresi PDF menghemat %.1f KB', $savedBytes / 1024));
        }

        return self::SUCCESS;
    }
}
