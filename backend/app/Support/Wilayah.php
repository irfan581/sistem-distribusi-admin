<?php

namespace App\Support;

/**
 * Kode area dan nama wilayah pengiriman.
 *
 * Daftar ini sempat menjadi tabel `wilayahs` berikut halaman adminnya, supaya
 * tiap wilayah bisa ditandai jauh atau dekat. Penandaan itu dilepas September
 * 2026 karena tidak pernah dipakai: di produksi tabelnya nol baris, sehingga
 * setiap kiriman selamanya berlabel "Jarak belum ditandai" dan halaman
 * statistik menampilkan kode mentah alih-alih nama wilayah.
 *
 * Begitu penandaannya hilang, satu-satunya alasan tabel itu ada ikut hilang.
 * Yang tersisa empat belas nama kabupaten yang praktis tidak pernah berubah —
 * dan sebuah tabel tanpa halaman pengelola, yang harus disunting lewat
 * database, justru lebih merepotkan daripada daftar di sini.
 */
class Wilayah
{
    /** Kode area → nama wilayah. */
    public const NAMA = [
        'BJN'  => 'Bojonegoro',
        'BLT'  => 'Blitar',
        'J86'  => 'Jombang',
        'KDR'  => 'Kediri',
        'MDN'  => 'Madiun',
        'MGT'  => 'Magetan',
        'NGJ'  => 'Nganjuk',
        'NGW'  => 'Ngawi',
        'PNR'  => 'Ponorogo',
        'TLG'  => 'Tulungagung',
        'TRG'  => 'Trenggalek',
        'TRT'  => 'Tuban',
        'TTSJ' => 'Tuban Selatan / Jenu',
        'WNG'  => 'Wonogiri',
    ];

    /** Nama wilayah untuk sebuah kode, atau kodenya sendiri bila tak dikenal. */
    public static function nama(?string $kode): ?string
    {
        if (blank($kode)) {
            return null;
        }

        return self::NAMA[$kode] ?? $kode;
    }

    /**
     * Kode area dari nama wilayah yang tertulis di master toko.
     *
     * Dicocokkan tanpa membedakan huruf besar kecil dan spasi berlebih, karena
     * kolom `wilayah` di master toko diisi manual dan lewat unggahan Excel —
     * "bojonegoro" dan "Bojonegoro " sama-sama muncul di sana.
     */
    public static function kode(?string $nama): ?string
    {
        if (blank($nama)) {
            return null;
        }

        $cari = mb_strtolower(trim($nama));

        foreach (self::NAMA as $kode => $resmi) {
            if (mb_strtolower($resmi) === $cari) {
                return $kode;
            }
        }

        return null;
    }
}
