<?php

namespace App\Support;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\URL;

/**
 * Satu tempat menentukan alamat berkas sebuah pesan chat.
 *
 * Dulu dua sisi aplikasi menyimpan dan membaca kolom yang berbeda, dan
 * keduanya sama-sama "bekerja" selama diuji sendiri-sendiri:
 *
 * - Dashboard admin menulis `file_url` (sudah berupa alamat) dan tidak pernah
 *   menulis `file_path`.
 * - Aplikasi sales dan sopir menulis `file_path` dan tidak pernah menulis
 *   `file_url`.
 * - `ChatController::formatMessage` membaca `file_path` — jadi foto kiriman
 *   admin tidak pernah tampil di HP.
 * - `ChatMessageSent` membaca `file_url` — jadi foto kiriman sopir dan sales,
 *   termasuk seluruh laporan bongkar, tidak pernah tampil saat datang
 *   realtime. Baru muncul setelah chat ditutup dan dibuka lagi.
 *
 * Karena itu `file_path` yang dijadikan patokan, dan `file_url` tetap dibaca
 * sebagai cadangan supaya pesan lama kiriman admin tidak jadi kosong.
 */
class BerkasChat
{
    public static function url(ChatMessage $pesan): ?string
    {
        return self::tautan($pesan, 'penuh');
    }

    /**
     * Versi kecil untuk gelembung chat, yang lebarnya cuma 300px.
     *
     * Jatuh kembali ke berkas penuh, karena hanya foto laporan bongkar yang
     * punya thumbnail — gambar chat biasa tidak, dan pesan lama juga belum.
     */
    public static function thumb(ChatMessage $pesan): ?string
    {
        return self::tautan($pesan, filled($pesan->thumb_path) ? 'kecil' : 'penuh');
    }

    /**
     * Tautan bertanda tangan yang berlaku 12 jam, bukan `/storage/...` lagi.
     *
     * Lampiran chat dulu disajikan Nginx langsung, jadi siapa pun yang
     * memegang alamatnya bisa membukanya tanpa login — termasuk foto bukti
     * bongkar yang dikirim ke grup laporan. Sekarang lewat route yang
     * memeriksa apakah pemintanya memang ikut percakapan itu.
     *
     * Bertanda tangan, bukan berpenjaga token, karena dashboard memasang
     * gambarnya dengan `<img src>` dan tag itu tidak pernah mengirim header
     * Authorization.
     *
     * Masa berlakunya juga alasan tautan ini TIDAK boleh disimpan ke kolom
     * `file_url`: yang tersimpan akan mati sendiri besok paginya. Dibentuk
     * setiap kali pesannya dikirim ke client.
     */
    private static function tautan(ChatMessage $pesan, string $ukuran): ?string
    {
        if (blank($pesan->file_path) && blank($pesan->file_url) && blank($pesan->thumb_path)) {
            return null;
        }

        return URL::temporarySignedRoute('berkas.chat', now()->addHours(12), [
            'id' => $pesan->id, 'ukuran' => $ukuran,
        ]);
    }
}
