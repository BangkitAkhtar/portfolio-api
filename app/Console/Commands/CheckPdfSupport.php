<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckPdfSupport extends Command
{
    protected $signature = 'pdf:check';

    protected $description = 'Cek kemampuan server untuk kompresi & thumbnail PDF (Ghostscript / Imagick / exec)';

    public function handle(): int
    {
        $this->info('=== Kemampuan Server untuk PDF ===');
        $this->newLine();

        // 1. Cek apakah exec() diizinkan
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $execOk = function_exists('exec') && !in_array('exec', $disabled, true);
        $this->line('exec() diizinkan      : ' . ($execOk ? '<fg=green>YA</>' : '<fg=red>TIDAK (diblokir hosting)</>'));

        // 2. Cek Ghostscript (mesin kompresi PDF sebenarnya)
        $gsPath = null;
        $gsVersion = null;
        if ($execOk) {
            foreach (['gs', '/usr/bin/gs', '/usr/local/bin/gs', '/opt/bin/gs'] as $candidate) {
                $out = [];
                $code = 0;
                @exec(escapeshellcmd($candidate) . ' --version 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out)) {
                    $gsPath = $candidate;
                    $gsVersion = trim($out[0]);
                    break;
                }
            }
        }
        $this->line('Ghostscript (gs)      : ' . ($gsPath
            ? "<fg=green>ADA</> (v{$gsVersion} di {$gsPath})"
            : '<fg=red>TIDAK ADA</>'));

        // 3. Cek Imagick (untuk bikin thumbnail halaman pertama PDF)
        $imagick = extension_loaded('imagick');
        $imagickPdf = false;
        if ($imagick) {
            try {
                $formats = \Imagick::queryFormats('PDF');
                $imagickPdf = !empty($formats);
            } catch (\Throwable $e) {
                $imagickPdf = false;
            }
        }
        $this->line('Imagick               : ' . ($imagick ? '<fg=green>ADA</>' : '<fg=red>TIDAK ADA</>'));
        $this->line('Imagick baca PDF      : ' . ($imagickPdf ? '<fg=green>BISA</>' : '<fg=red>TIDAK BISA</>'));

        // 4. Batas upload PHP
        $this->newLine();
        $this->line('upload_max_filesize   : ' . ini_get('upload_max_filesize'));
        $this->line('post_max_size         : ' . ini_get('post_max_size'));
        $this->line('memory_limit          : ' . ini_get('memory_limit'));

        // Kesimpulan
        $this->newLine();
        $this->info('=== Kesimpulan ===');
        if ($gsPath) {
            $this->line('<fg=green>PDF BISA dikompres otomatis</> saat upload (pakai Ghostscript).');
        } else {
            $this->line('<fg=yellow>PDF TIDAK bisa dikompres di server ini.</> File akan disimpan apa adanya.');
            $this->line('Solusi: kompres PDF dulu sebelum upload (mis. lewat iLovePDF/Smallpdf),');
            $this->line('atau minta hosting memasang Ghostscript.');
        }
        if ($imagickPdf) {
            $this->line('<fg=green>Thumbnail PDF BISA dibuat otomatis</> (preview cepat & ringan).');
        } else {
            $this->line('<fg=yellow>Thumbnail PDF tidak bisa dibuat di server.</>');
            $this->line('Preview tetap jalan, tapi dirender browser (pakai viewer bawaan).');
        }

        return self::SUCCESS;
    }
}
