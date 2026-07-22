<?php

namespace App\Support;

/**
 * Pemrosesan PDF yang sifatnya "usaha terbaik" (best-effort).
 *
 * Shared hosting sering tidak punya Ghostscript maupun Imagick, jadi semua method
 * di sini SENGAJA gagal dalam diam: upload tidak boleh ikut gagal hanya karena
 * kompresi/thumbnail tidak bisa dilakukan.
 */
class PdfProcessor
{
    /** Apakah exec() benar-benar bisa dipakai di server ini. */
    public static function canExec(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('exec') && !in_array('exec', $disabled, true);
    }

    /** Lokasi binary Ghostscript, atau null kalau tidak ada. */
    public static function ghostscriptPath(): ?string
    {
        if (!self::canExec()) {
            return null;
        }

        foreach (['gs', '/usr/bin/gs', '/usr/local/bin/gs', '/opt/bin/gs'] as $candidate) {
            $out = [];
            $code = 1;
            @exec(escapeshellcmd($candidate) . ' --version 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Apakah Imagick tersedia DAN bisa membaca PDF (perlu delegate Ghostscript). */
    public static function canThumbnail(): bool
    {
        if (!extension_loaded('imagick')) {
            return false;
        }

        try {
            return !empty(\Imagick::queryFormats('PDF'));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Kompres PDF di tempat. Hasil hanya dipakai kalau benar-benar lebih kecil.
     *
     * @return bool true kalau file berhasil diperkecil
     */
    public static function compress(string $absolutePath): bool
    {
        try {
            if (!is_file($absolutePath)) {
                return false;
            }

            $gs = self::ghostscriptPath();
            if (!$gs) {
                return false;
            }

            $tmp = $absolutePath . '.compressed.pdf';

            // /ebook = target ~150dpi: seimbang antara ukuran kecil & teks tetap terbaca
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
                if (filesize($tmp) < filesize($absolutePath)) {
                    @rename($tmp, $absolutePath);

                    return true;
                }
                @unlink($tmp);
            } elseif (is_file($tmp)) {
                @unlink($tmp);
            }
        } catch (\Throwable) {
            // diabaikan: kompresi itu bonus, bukan syarat
        }

        return false;
    }

    /**
     * Render halaman pertama PDF jadi "<file>.pdf.webp".
     * Frontend menebak nama ini; kalau tidak ada, ia render sendiri via PDF.js.
     *
     * @return bool true kalau thumbnail berhasil dibuat
     */
    public static function thumbnail(string $absolutePath): bool
    {
        try {
            if (!is_file($absolutePath) || !self::canThumbnail()) {
                return false;
            }

            $im = new \Imagick();
            $im->setResolution(150, 150);
            $im->readImage($absolutePath . '[0]'); // halaman pertama saja
            $im->setImageBackgroundColor('white');
            $im = $im->flattenImages();            // buang alpha supaya tidak jadi hitam
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality(82);
            $im->thumbnailImage(800, 0);           // lebar maks 800px, rasio dijaga
            $im->writeImage($absolutePath . '.webp');
            $im->clear();
            $im->destroy();

            return true;
        } catch (\Throwable) {
            // diabaikan: frontend punya fallback render sendiri
        }

        return false;
    }
}
