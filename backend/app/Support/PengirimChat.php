<?php

namespace App\Support;

use App\Models\drivers;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menerjemahkan pasangan sender_type/sender_id menjadi nama orang.
 *
 * Dipusatkan di sini karena tabel chat polimorfik sementara kodenya dulu
 * menebak dengan cabang dua: kalau bukan User, dianggap Sales. Begitu sopir
 * ikut mengirim pesan, tebakan itu menampilkan NAMA SALES yang kebetulan
 * ber-id sama — bukan sekadar salah label, tapi mengatributkan ucapan seseorang
 * ke orang lain. Setiap penampil pesan sekarang lewat sini.
 */
class PengirimChat
{
    /** Nama peran saat orangnya tidak ditemukan lagi (mis. akun terhapus). */
    private const CADANGAN = [
        User::class    => 'Admin',
        Sales::class   => 'Sales',
        drivers::class => 'Sopir',
    ];

    public static function nama(?string $tipe, int|string|null $id): string
    {
        if ($tipe === null || $id === null) {
            return 'Tidak dikenal';
        }

        $orang = match ($tipe) {
            User::class    => User::find($id)?->name,
            Sales::class   => Sales::find($id)?->nama,
            drivers::class => drivers::find($id)?->nama_driver,
            default        => null,
        };

        return $orang ?? (self::CADANGAN[$tipe] ?? 'Tidak dikenal');
    }

    /**
     * Peran pengirim, untuk aplikasi yang ingin membedakan tampilannya —
     * pesan sopir enak dibedakan dari pesan kantor di grup laporan.
     */
    public static function peran(?string $tipe): string
    {
        return match ($tipe) {
            User::class    => 'admin',
            Sales::class   => 'sales',
            drivers::class => 'sopir',
            default        => 'lain',
        };
    }

    /**
     * Versi borongan: satu query per jenis, bukan satu query per pesan.
     *
     * @param  Collection  $baris  Koleksi apa pun yang punya $tipeKolom & $idKolom.
     * @return array<string, array<int|string, string>> peta[tipe][id] = nama
     */
    public static function peta(Collection $baris, string $tipeKolom = 'sender_type', string $idKolom = 'sender_id'): array
    {
        $peta = [];

        foreach ([User::class => 'name', Sales::class => 'nama', drivers::class => 'nama_driver'] as $kelas => $kolomNama) {
            $ids = $baris->where($tipeKolom, $kelas)->pluck($idKolom)->unique()->filter();

            $peta[$kelas] = $ids->isEmpty()
                ? []
                : $kelas::whereIn('id', $ids)->pluck($kolomNama, 'id')->all();
        }

        return $peta;
    }

    /** Membaca hasil peta(); jatuh ke nama peran bila orangnya sudah tidak ada. */
    public static function dariPeta(array $peta, ?string $tipe, int|string|null $id): string
    {
        if ($tipe === null || $id === null) {
            return 'Tidak dikenal';
        }

        return $peta[$tipe][$id] ?? (self::CADANGAN[$tipe] ?? 'Tidak dikenal');
    }
}
