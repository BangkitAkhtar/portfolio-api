<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ServerCheck extends Command
{
    protected $signature = 'server:check';

    protected $description = 'Audit performa & keamanan server (OPcache, cache Laravel, PHP config)';

    public function handle(): int
    {
        $todo = [];

        $this->info('╔══════════════════════════════════════╗');
        $this->info('║   AUDIT SERVER — portfolio-api       ║');
        $this->info('╚══════════════════════════════════════╝');

        // ---------- 1. PHP ----------
        $this->newLine();
        $this->line('<options=bold>1. PHP</>');
        $this->line('   Versi         : ' . PHP_VERSION);
        if (version_compare(PHP_VERSION, '8.2', '<')) {
            $todo[] = 'Naikkan versi PHP ke 8.3/8.4 di cPanel > MultiPHP Manager (bisa 10-20% lebih cepat).';
        }

        // ---------- 2. OPcache (dampak terbesar untuk Laravel) ----------
        $this->newLine();
        $this->line('<options=bold>2. OPcache</> <fg=gray>(pengaruh terbesar ke kecepatan PHP)</>');
        if (!function_exists('opcache_get_status')) {
            $this->line('   Status        : <fg=red>EKSTENSI TIDAK ADA</>');
            $todo[] = 'Aktifkan OPcache: cPanel > Select PHP Version > Extensions > centang "opcache".';
        } else {
            $st = @opcache_get_status(false);
            $enabled = is_array($st) && ($st['opcache_enabled'] ?? false);
            $this->line('   Status        : ' . ($enabled ? '<fg=green>AKTIF</>' : '<fg=red>TIDAK AKTIF</>'));

            if (!$enabled) {
                $todo[] = 'OPcache terpasang tapi mati. cPanel > MultiPHP INI Editor > Editor Mode, tambahkan: opcache.enable=1';
            } else {
                $mem = $st['memory_usage'] ?? [];
                $stats = $st['opcache_statistics'] ?? [];
                $used = ($mem['used_memory'] ?? 0) / 1048576;
                $free = ($mem['free_memory'] ?? 0) / 1048576;
                $hits = $stats['hits'] ?? 0;
                $miss = $stats['misses'] ?? 0;
                $rate = ($hits + $miss) > 0 ? ($hits / ($hits + $miss) * 100) : 0;

                $this->line(sprintf('   Memori        : %.1f MB terpakai, %.1f MB sisa', $used, $free));
                $this->line(sprintf('   Hit rate      : %.1f%%', $rate));

                if ($free < 8) {
                    $todo[] = 'Memori OPcache menipis. Tambah di INI Editor: opcache.memory_consumption=128';
                }
                if (($stats['num_cached_keys'] ?? 0) >= (($stats['max_cached_keys'] ?? PHP_INT_MAX) * 0.9)) {
                    $todo[] = 'Slot file OPcache hampir penuh. Tambah: opcache.max_accelerated_files=20000';
                }
            }
        }

        // ---------- 3. Cache Laravel ----------
        $this->newLine();
        $this->line('<options=bold>3. Cache Laravel</>');
        $checks = [
            'Config' => base_path('bootstrap/cache/config.php'),
            'Route'  => base_path('bootstrap/cache/routes-v7.php'),
            'Event'  => base_path('bootstrap/cache/events.php'),
        ];
        $anyMissing = false;
        foreach ($checks as $label => $file) {
            $ok = file_exists($file);
            if (!$ok) {
                $anyMissing = true;
            }
            $this->line(sprintf('   %-13s : %s', $label, $ok ? '<fg=green>ter-cache</>' : '<fg=yellow>belum</>'));
        }
        if ($anyMissing) {
            $todo[] = 'Jalankan: php artisan config:cache && php artisan route:cache && php artisan view:cache';
            $todo[] = 'CATATAN: setelah itu, tiap kali edit .env WAJIB "php artisan optimize:clear" dulu.';
        }

        // Autoloader composer
        $optimized = file_exists(base_path('vendor/composer/autoload_static.php'));
        $this->line('   Autoloader    : ' . ($optimized ? '<fg=green>ada</>' : '<fg=yellow>belum dioptimasi</>'));
        if (!$optimized) {
            $todo[] = 'Jalankan: composer install --optimize-autoloader --no-dev';
        }

        // ---------- 4. Konfigurasi aplikasi ----------
        $this->newLine();
        $this->line('<options=bold>4. Konfigurasi Aplikasi</>');
        $debug = config('app.debug');
        $env = config('app.env');
        $this->line('   APP_ENV       : ' . $env);
        $this->line('   APP_DEBUG     : ' . ($debug ? '<fg=red>true (BAHAYA)</>' : '<fg=green>false</>'));
        if ($debug) {
            $todo[] = 'KEAMANAN: set APP_DEBUG=false di .env — kalau true, halaman error membocorkan isi .env (password DB, token).';
        }
        $this->line('   CACHE_STORE   : ' . config('cache.default'));
        $this->line('   SESSION_DRIVER: ' . config('session.driver'));

        // ---------- 5. Batas PHP ----------
        $this->newLine();
        $this->line('<options=bold>5. Batas PHP</>');
        foreach ([
            'memory_limit', 'max_execution_time', 'upload_max_filesize',
            'post_max_size', 'max_input_vars',
        ] as $k) {
            $this->line(sprintf('   %-19s : %s', $k, ini_get($k)));
        }
        $maxExec = (int) ini_get('max_execution_time');
        if ($maxExec > 0 && $maxExec < 60) {
            $todo[] = 'max_execution_time hanya ' . $maxExec . 's — kompresi PDF besar (Ghostscript) bisa timeout. Naikkan ke 120 di INI Editor.';
        }

        // ---------- Ringkasan ----------
        $this->newLine();
        $this->info('═══════════ YANG PERLU DILAKUKAN ═══════════');
        if (empty($todo)) {
            $this->line('<fg=green>Semua sudah optimal. Tidak ada yang perlu diubah.</>');
        } else {
            foreach ($todo as $i => $t) {
                $this->line('  ' . ($i + 1) . '. ' . $t);
            }
        }
        $this->newLine();

        return self::SUCCESS;
    }
}
