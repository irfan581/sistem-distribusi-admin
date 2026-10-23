<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Perkakas berkas gambar selama dan sesudah perpindahan ke disk privat.
 *
 * Foto lama tersebar di dua tempat: yang sudah dipindah ada di disk `local`
 * (storage/app/private, tanpa pintu web), yang belum masih di `public`. Selama
 * masa itu, setiap kode yang membaca atau menghapus berkas harus melihat
 * keduanya — kalau hanya melihat satu, foto yang belum pindah dianggap hilang
 * dan yang sudah pindah tidak pernah terhapus.
 *
 * Dikumpulkan di sini supaya aturannya satu, dan supaya sesudah seluruh berkas
 * pindah cukup satu tempat yang dirapikan: buang `public` dari [URUTAN].
 */
class Berkas
{
    /**
     * Urutan pencarian. Yang privat lebih dulu karena ke sanalah semua berkas
     * baru ditulis; `public` tinggal sisa yang belum sempat dipindah.
     */
    private const URUTAN = ['local', 'public'];

    /** Disk yang benar-benar memuat berkas ini, atau null kalau tidak ada. */
    public static function disk(?string $path): ?Filesystem
    {
        if (blank($path)) {
            return null;
        }

        foreach (self::URUTAN as $nama) {
            if (Storage::disk($nama)->exists($path)) {
                return Storage::disk($nama);
            }
        }

        return null;
    }

    public static function ada(?string $path): bool
    {
        return self::disk($path) !== null;
    }

    /**
     * Hapus dari mana pun berkasnya berada.
     *
     * Menerima satu path atau daftar, karena sebagian pemanggil menghapus foto
     * masuk dan foto pulang sekaligus.
     */
    public static function hapus(string|array|null $path): void
    {
        foreach ((array) $path as $satu) {
            if (blank($satu)) {
                continue;
            }

            foreach (self::URUTAN as $nama) {
                Storage::disk($nama)->delete($satu);
            }
        }
    }
}
