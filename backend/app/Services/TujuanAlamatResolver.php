<?php

namespace App\Services;

use App\Models\DaftarMuatItem;
use App\Models\KunjunganToko;
use App\Models\Toko;
use Illuminate\Support\Collection;

/**
 * Mengisi alamat tujuan pengiriman tanpa menyentuh master toko.
 *
 * `lokasi_pengiriman` di daftar_muat_items adalah teks bebas yang diketik
 * admin — tidak ada dropdown pilih toko, jadi tidak ada relasi pasti ke
 * tabel tokos. Di sini kita coba cocokkan namanya (persis dulu, lalu mirip
 * >=80% pakai similar_text seperti yang sudah pernah dipakai manual), lalu
 * ambil alamat dari kunjungan_tokos terakhir milik toko itu — itu alamat
 * asli hasil GPS sales yang datang ke sana, bukan tebakan.
 *
 * Sengaja tidak menambah kolom apa pun di tokos. Hasil pencocokan disimpan
 * di baris daftar_muat_items sendiri supaya tidak dihitung ulang tiap kali
 * dibuka — lihat kolom alamat_resolved_at.
 */
class TujuanAlamatResolver
{
    private const AMBANG_MIRIP = 80.0;

    /**
     * Resolve seluruh item yang belum pernah dicoba dicocokkan.
     *
     * Daftar toko diambil sekali per pemanggilan, bukan per item, supaya
     * satu halaman berisi puluhan tujuan tidak memicu puluhan query yang
     * sama ke tabel tokos.
     *
     * @param Collection<int, DaftarMuatItem> $items
     */
    public function resolveBanyak(Collection $items): void
    {
        $belumSelesai = $items->filter(fn (DaftarMuatItem $item) => $item->alamat_resolved_at === null);

        if ($belumSelesai->isEmpty()) {
            return;
        }

        $tokos = Toko::select('id', 'nama_kios')->get();

        foreach ($belumSelesai as $item) {
            $this->resolve($item, $tokos);
        }
    }

    public function resolve(DaftarMuatItem $item, ?Collection $tokos = null): void
    {
        if ($item->alamat_resolved_at !== null) {
            return;
        }

        $toko = $this->cariToko($item->lokasi_pengiriman, $tokos ?? Toko::select('id', 'nama_kios')->get());

        $kunjungan = $toko ? $this->alamatTerakhir($toko) : null;

        $item->forceFill([
            'toko_id'            => $toko?->id,
            'alamat_otomatis'    => $kunjungan?->alamat,
            'alamat_lat'         => $kunjungan?->lat,
            'alamat_lng'         => $kunjungan?->lng,
            'alamat_resolved_at' => now(),
        ])->save();
    }

    /**
     * Kunjungan sales paling baru ke toko tertentu yang punya alamat —
     * sumber tunggal alamat/koordinat untuk tujuan pengiriman, dipakai baik
     * oleh pencocokan otomatis (lazy, di atas) maupun oleh pemilihan toko
     * langsung saat admin membuat daftar muat (lihat DaftarMuatService).
     */
    public function alamatTerakhir(Toko $toko): ?KunjunganToko
    {
        return KunjunganToko::where('toko_id', $toko->id)
            ->whereNotNull('alamat')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param Collection<int, Toko> $tokos
     */
    private function cariToko(string $lokasiPengiriman, Collection $tokos): ?Toko
    {
        $kunci = $this->normalisasi($lokasiPengiriman);

        if ($kunci === '') {
            return null;
        }

        $persis = $tokos->first(fn (Toko $t) => $this->normalisasi($t->nama_kios) === $kunci);
        if ($persis) {
            return $persis;
        }

        $terbaik  = null;
        $skorTerbaik = 0.0;

        foreach ($tokos as $t) {
            similar_text($kunci, $this->normalisasi($t->nama_kios), $persen);
            if ($persen > $skorTerbaik) {
                $skorTerbaik = $persen;
                $terbaik = $t;
            }
        }

        return $skorTerbaik >= self::AMBANG_MIRIP ? $terbaik : null;
    }

    private function normalisasi(string $teks): string
    {
        return mb_strtolower(trim($teks));
    }
}
