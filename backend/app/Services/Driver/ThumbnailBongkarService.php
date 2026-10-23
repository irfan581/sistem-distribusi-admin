<?php

namespace App\Services\Driver;

use App\Models\BongkarFoto;
use App\Support\Berkas;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * Versi kecil foto bukti, untuk daftar kiriman di aplikasi sales.
 *
 * Aslinya 1280px kualitas 80 (~200-400 KB); versi ini 320px kualitas 70
 * (~20-30 KB). Sales menggulir aliran foto seperti membaca grup WhatsApp,
 * jadi yang menentukan pengalaman bukan ketajaman satu foto melainkan berapa
 * cepat dua puluh foto muncul di sinyal seadanya.
 *
 * Dipakai dua pemanggil: ProsesFotoBongkar untuk foto yang baru masuk, dan
 * perintah `bongkar:thumbnail` untuk foto yang sudah terlanjur tersimpan.
 */
class ThumbnailBongkarService
{
    public const LEBAR    = 320;
    public const KUALITAS = 70;

    /**
     * @return bool true bila kolom thumb terisi setelah pemanggilan ini.
     */
    public function buat(BongkarFoto $foto, bool $paksa = false): bool
    {
        if (!$paksa && Berkas::ada($foto->thumb)) {
            return true;
        }

        // Foto asalnya dibaca dari mana pun ia berada: yang sudah pindah ada
        // di disk privat, yang belum masih di disk lama. Thumbnail-nya selalu
        // ditulis ke disk privat, supaya tidak ada berkas baru yang lahir di
        // tempat yang sedang ditinggalkan.
        $asal = Berkas::disk($foto->foto);

        if ($asal === null) {
            return false;
        }

        $disk = Storage::disk('local');

        // Thumbnail ditaruh di subfolder rit yang sama supaya menghapus satu
        // folder rit tetap membuang bukti berikut turunannya sekaligus.
        $tujuan = dirname($foto->foto) . '/thumb/' . basename($foto->foto);

        try {
            $gambar = Image::read($asal->path($foto->foto))
                ->scaleDown(width: self::LEBAR);

            $disk->put($tujuan, (string) $gambar->toJpeg(self::KUALITAS));
        } catch (Throwable $e) {
            // Foto besarnya tetap utuh dan tetap bisa ditampilkan, jadi
            // kegagalan di sini menurunkan mutu tampilan — bukan menghilangkan
            // bukti. Karena itu tidak dilempar ulang: satu foto aneh tidak
            // boleh menggagalkan job yang juga memindahkan berkas.
            Log::warning("Thumbnail foto {$foto->id} gagal dibuat: {$e->getMessage()}");
            return false;
        }

        $foto->updateQuietly(['thumb' => $tujuan]);

        return true;
    }
}
