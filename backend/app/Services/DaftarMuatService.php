<?php

namespace App\Services;

use App\Models\kendaraans;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\Toko;
use App\Services\TujuanAlamatResolver;
use Exception;
use Illuminate\Support\Facades\DB;

class DaftarMuatService
{
    public function __construct(private readonly TujuanAlamatResolver $alamat) {}

    /**
     * Tujuan yang punya toko_id (dipilih admin dari master toko) langsung
     * dibekali alamat/koordinat dari kunjungan sales terakhir ke toko itu,
     * saat itu juga — bukan menunggu di-resolve lazy seperti tujuan lama
     * yang masih teks bebas. Hasilnya snapshot: kalau toko itu dikunjungi
     * ulang nanti dengan alamat berbeda, baris ini tidak ikut berubah,
     * sama seperti pola snapshot di bongkar_fotos.
     *
     * @return array kolom siap disisipkan ke DaftarMuatItem::create/updateOrCreate
     */
    private function kolomAlamat(array $item): array
    {
        if (empty($item['toko_id'])) {
            return [
                'toko_id'            => null,
                'lokasi_pengiriman'  => $item['lokasi_pengiriman'],
                'alamat_otomatis'    => null,
                'alamat_lat'         => null,
                'alamat_lng'         => null,
                'alamat_resolved_at' => null,
            ];
        }

        $toko = Toko::findOrFail($item['toko_id']);

        // Admin hanya boleh mengisi alamat toko yang masih kosong, bukan menimpa yang sudah ada.
        $diketik = trim((string) ($item['alamat'] ?? ''));
        if ($diketik !== '' && blank($toko->alamat)) {
            $toko->update(['alamat' => $diketik, 'alamat_manual' => true]);
        }

        $kunjungan = $toko->lat === null || blank($toko->alamat) ? $this->alamat->alamatTerakhir($toko) : null;

        return [
            'toko_id'            => $toko->id,
            // Kode area diambil dari wilayah tokonya, bukan diketik admin.
            // Sebelumnya diketik manual tiap membuat rit — pekerjaan hafalan
            // yang tidak berguna saat itu juga, sehingga seperempat kiriman
            // di produksi kode areanya kosong dan tidak bisa dikategorikan.
            // Kalau admin tetap mengisinya sendiri, isian itu yang menang.
            'kode_area'          => ($item['kode_area'] ?? null) ?: \App\Support\Wilayah::kode($toko->wilayah),
            // Nama toko dipakai sebagai lokasi_pengiriman untuk kompatibilitas
            // tampilan lama (surat jalan, pencarian) yang masih baca kolom ini.
            'lokasi_pengiriman'  => $toko->nama_kios,
            'alamat_otomatis'    => $toko->alamat ?: $kunjungan?->alamat,
            'alamat_lat'         => $toko->lat ?? $kunjungan?->lat,
            'alamat_lng'         => $toko->lng ?? $kunjungan?->lng,
            'alamat_resolved_at' => now(),
        ];
    }

    /** Pesan untuk sopir; isian kosong atau hanya spasi disimpan sebagai null. */
    private function catatanAdmin(array $item): ?string
    {
        $catatan = trim((string) ($item['catatan_admin'] ?? ''));

        return $catatan === '' ? null : $catatan;
    }

    public function prosesPendaftaranMuat(array $data): DaftarMuat
    {
        $items    = $data['items'] ?? [];
        $totalTon = (float) ($data['total_muatan_ton'] ?? 0);

        if (!empty($data['kendaraan_id'])) {
            $kendaraan = kendaraans::findOrFail($data['kendaraan_id']);
            if ($totalTon > $kendaraan->kapasitas_muatan_ton) {
                $totalKg = round($totalTon * 1000);
                $kapKg   = round($kendaraan->kapasitas_muatan_ton * 1000);
                throw new Exception("Muatan Overload! Total {$totalKg} Kg melebihi kapasitas {$kendaraan->nomor_polisi} ({$kapKg} Kg).");
            }
        }

        // Satu transaksi supaya notifikasi driver berangkat setelah tujuan tersimpan.
        return DB::transaction(function () use ($data, $items, $totalTon) {
            $muat = DaftarMuat::create([
                'kendaraan_id'     => $data['kendaraan_id'] ?? null,
                'driver_id'        => $data['driver_id'] ?? null,
                'tanggal_muatan'   => $data['tanggal_muatan'],
                'nomor_rit'        => $data['nomor_rit'] ?? 1,
                'jenis_muatan'     => $data['jenis_muatan'] ?? 'non_subsidi',
                'total_muatan_ton' => $totalTon,
            ]);

            foreach ($items as $i => $item) {
                DaftarMuatItem::create(array_merge([
                    'daftar_muat_id'    => $muat->id,
                    'urutan'            => $i + 1,
                    'jenis_muatan'      => $item['jenis_muatan'] ?? 'non_subsidi',
                    'kode_area'         => $item['kode_area'] ?? null,
                    'catatan_admin'     => $this->catatanAdmin($item),
                    'total_muatan_ton'  => null,
                ], $this->kolomAlamat($item)));
            }

            return $muat;
        });
    }

    public function updateMuat(DaftarMuat $muat, array $data): DaftarMuat
    {
        $items    = $data['items'] ?? [];
        $totalTon = (float) ($data['total_muatan_ton'] ?? 0);

        $this->pastikanBolehUbahTujuan($muat, $items);

        if (!empty($data['kendaraan_id'])) {
            $kendaraan = kendaraans::findOrFail($data['kendaraan_id']);
            if ($totalTon > $kendaraan->kapasitas_muatan_ton) {
                $totalKg = round($totalTon * 1000);
                $kapKg   = round($kendaraan->kapasitas_muatan_ton * 1000);
                throw new Exception("Muatan Overload! Total {$totalKg} Kg melebihi kapasitas {$kendaraan->nomor_polisi} ({$kapKg} Kg).");
            }
        }

        return DB::transaction(function () use ($muat, $data, $items, $totalTon) {
            $muat->update([
                'kendaraan_id'     => $data['kendaraan_id'] ?? null,
                'driver_id'        => $data['driver_id'] ?? null,
                'tanggal_muatan'   => $data['tanggal_muatan'],
                'nomor_rit'        => $data['nomor_rit'] ?? $muat->nomor_rit ?? 1,
                'jenis_muatan'     => $data['jenis_muatan'] ?? $muat->jenis_muatan ?? 'non_subsidi',
                'total_muatan_ton' => $totalTon,
            ]);

            $this->sinkronkanItems($muat, $items);

            return $muat;
        });
    }

    /**
     * Rit yang sudah berjalan tidak boleh diubah tujuannya diam-diam, dan tujuan
     * yang sudah punya bukti bongkar tidak boleh hilang begitu saja.
     */
    private function pastikanBolehUbahTujuan(DaftarMuat $muat, array $items): void
    {
        $tujuanBerubah = $this->tujuanBerubah($muat, $items);

        if ($tujuanBerubah && $muat->sudahJalan()) {
            throw new Exception(
                "Rit ini sudah berstatus \"{$muat->statusPerjalanan()}\". Hubungi driver dulu sebelum mengubah daftar tujuan."
            );
        }

        if (!$tujuanBerubah) {
            return;
        }

        $adaBukti = $muat->items()
            ->where('urutan', '>', count($items))
            ->whereHas('bongkarFotos')
            ->exists();

        if ($adaBukti) {
            throw new Exception(
                'Tujuan yang dihapus sudah punya foto bongkar. Hapus dulu fotonya atau biarkan tujuan itu tetap ada.'
            );
        }
    }

    /**
     * Membandingkan tujuan yang tersimpan dengan yang dikirim. Menyimpan ulang
     * tanpa mengubah apa pun tidak dianggap perubahan, supaya admin tetap bisa
     * membetulkan kendaraan atau driver pada rit yang sudah jalan.
     */
    private function tujuanBerubah(DaftarMuat $muat, array $items): bool
    {
        $sekarang = $muat->items()->orderBy('urutan')->get()
            ->map(fn ($i) => [
                'lokasi_pengiriman' => $i->lokasi_pengiriman,
                'toko_id'           => $i->toko_id,
                'jenis_muatan'      => $i->jenis_muatan ?? 'non_subsidi',
                'kode_area'         => $i->kode_area,
            ])
            ->all();

        $diminta = array_map(fn ($i) => [
            'lokasi_pengiriman' => $i['lokasi_pengiriman'],
            'toko_id'           => $i['toko_id'] ?? null,
            'jenis_muatan'      => $i['jenis_muatan'] ?? 'non_subsidi',
            'kode_area'         => $i['kode_area'] ?? null,
        ], array_values($items));

        return $sekarang !== $diminta;
    }

    /**
     * Menyelaraskan daftar tujuan tanpa menghapus lalu membuat ulang semuanya.
     *
     * Cara lama membuat id item berganti setiap kali admin menyimpan, walau
     * hanya membetulkan satu huruf. Sejak tujuan punya status bongkar dan foto
     * yang menempel padanya, id yang berganti berarti progres driver hilang dan
     * bukti bongkar jadi yatim.
     */
    private function sinkronkanItems(DaftarMuat $muat, array $items): void
    {
        foreach (array_values($items) as $i => $item) {
            $muat->items()->updateOrCreate(
                ['urutan' => $i + 1],
                array_merge([
                    'jenis_muatan'      => $item['jenis_muatan'] ?? 'non_subsidi',
                    'kode_area'         => $item['kode_area'] ?? null,
                    'catatan_admin'     => $this->catatanAdmin($item),
                    'total_muatan_ton'  => null,
                ], $this->kolomAlamat($item)),
            );
        }

        $muat->items()->where('urutan', '>', count($items))->delete();
    }
}
