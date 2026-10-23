<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

trait UploadGambar
{
    /**
     * Disk tujuan semua unggahan gambar.
     *
     * `local` berakar di `storage/app/private`, yang tidak punya pintu web sama
     * sekali. Sebelumnya `public`, yang disajikan Nginx lewat symlink
     * `public/storage` tanpa memeriksa siapa pun — termasuk swafoto presensi
     * karyawan.
     *
     * Dijadikan satu tetapan supaya tidak ada tempat yang tertinggal saat
     * diganti, dan supaya jelas bahwa tujuannya memang satu untuk semua jenis
     * gambar. Berkasnya dibaca kembali lewat BerkasController, bukan URL
     * langsung.
     */
    protected const DISK_GAMBAR = 'local';

    /**
     * Compress untuk foto profil / presensi / kunjungan.
     * Ukuran kecil karena hanya ditampilkan sebagai thumbnail.
     * width: 600px, quality: 50
     */
    public function uploadCompressed($file, $path)
    {
        return $this->uploadImage($file, $path, width: 600, quality: 50);
    }

    /**
     * Compress untuk foto chat — jernih, bisa dizoom & didownload.
     * width: 1280px, quality: 82 — cukup ringan tapi tetap tajam.
     */
    public function uploadChatImage($file, $path)
    {
        return $this->uploadImage($file, $path, width: 1280, quality: 82);
    }

    /**
     * Compress untuk foto bukti bongkar / surat jalan.
     * Lebih tajam dari foto presensi karena admin harus bisa membaca tulisan
     * di surat jalan dan menghitung barang di bak truk.
     * width: 1280px, quality: 80
     */
    public function uploadBukti($file, $path)
    {
        return $this->uploadImage($file, $path, width: 1280, quality: 80);
    }

    /**
     * Core image upload dengan parameter fleksibel.
     * Jika Intervention\Image / GD extension tidak tersedia,
     * otomatis fallback ke simple store tanpa kompresi.
     */
    private function uploadImage($file, $path, int $width, int $quality)
    {
        if (!$file || !$file->isValid()) {
            throw new \RuntimeException('File foto tidak valid atau gagal diterima server. Coba ulangi.');
        }

        // Coba kompresi dulu jika Intervention\Image & GD tersedia
        if (extension_loaded('gd')
            && class_exists(\Intervention\Image\ImageManager::class)
            && class_exists(\Intervention\Image\Drivers\Gd\Driver::class)
        ) {
            try {
                $filename = Str::uuid() . '.jpg';
                $fullPath = $path . '/' . $filename;

                $manager = new \Intervention\Image\ImageManager(
                    new \Intervention\Image\Drivers\Gd\Driver()
                );
                $image = $manager->read($file->getRealPath());

                // Hanya resize kalau lebar asli lebih dari target — jangan upscale
                if ($image->width() > $width) {
                    $image->scale(width: $width);
                }

                $encoded = $image->toJpeg(quality: $quality);
                Storage::disk(self::DISK_GAMBAR)->put($fullPath, (string) $encoded);

                Log::info("Upload image (compressed): $fullPath (width≤{$width}px, q{$quality})");

                return $fullPath;

            } catch (Throwable $e) {
                Log::warning('Image compression failed, falling back to original upload', [
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
                // Lanjut ke fallback di bawah
            }
        } else {
            Log::info("Image compression skipped (GD/Intervention not available), using original upload for: $path");
        }

        // Fallback: simpan file asli tanpa kompresi
        return $file->store($path, self::DISK_GAMBAR);
    }
}
