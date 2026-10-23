<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembungkus siaran realtime chat supaya kegagalannya tidak menjatuhkan
 * permintaan yang sedang berjalan.
 *
 * Seluruh event chat memakai ShouldBroadcastNow — dikirim serentak, bukan
 * lewat antrian. Artinya ketika server Reverb mati atau alamatnya berubah,
 * setiap panggilan broadcast() melempar galat dan menggagalkan aksi induknya:
 * pesan tidak bisa dikirim, laporan bongkar tidak bisa ditutup, padahal
 * barisnya sudah tersimpan di database. Pengirim melihat "gagal", lalu
 * mencoba lagi — dan pesannya berganda.
 *
 * Siaran hanyalah jalan pintas supaya pesan muncul tanpa menunggu layar
 * disegarkan. Kalau jalan pintas itu tertutup, isinya tetap ada dan tetap
 * terbaca begitu chat dibuka ulang. Karena itu kegagalannya dicatat, bukan
 * dilemparkan.
 */
class SiaranChat
{
    /**
     * @param  callable  $siaran  Bungkus panggilan broadcast()-nya, mis.
     *                            fn () => broadcast(new ChatMessageSent($chat))
     *                            Bentuk closure dipakai supaya rantai seperti
     *                            ->toOthers() ikut terlindungi.
     */
    public static function coba(callable $siaran, string $konteks = 'chat'): bool
    {
        try {
            $siaran();

            return true;
        } catch (Throwable $e) {
            Log::warning("Siaran realtime {$konteks} gagal: {$e->getMessage()}");

            return false;
        }
    }
}
