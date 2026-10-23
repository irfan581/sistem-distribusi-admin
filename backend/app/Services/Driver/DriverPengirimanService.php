<?php

namespace App\Services\Driver;

use App\Exceptions\TransisiStatusException;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\DaftarMuatLog;
use App\Models\drivers;
use Illuminate\Support\Facades\DB;

/**
 * Pekerjaan driver di lapangan: melihat rit hari ini, lalu melaporkan tiap
 * tujuan satu per satu.
 *
 * Rit tidak lagi punya tombol tahapan. Sebelumnya driver harus menekan "mulai
 * muat", "berangkat", dan "selesaikan rit" — tiga tap yang tidak mengubah apa
 * pun di dunia nyata, gampang terlupa saat buru-buru, dan membuat rit tampak
 * menggantung padahal barangnya sudah turun semua. Sekarang statusnya
 * disimpulkan dari laporan yang masuk (lihat DaftarMuat::selaraskanStatus).
 */
class DriverPengirimanService
{
    /**
     * Jalan keluar yang boleh dipilih driver untuk sebuah tujuan.
     *
     * Sengaja cuma dua. Menggolongkan jenis masalah — toko tutup, barang
     * ditolak, diterima sebagian — dikerjakan lewat telepon ke kantor, bukan
     * lewat tombol: keputusannya selalu butuh orang, dan daftar pilihan yang
     * panjang cuma bikin driver salah tekan.
     */
    private const JALAN_KELUAR = ['Selesai', 'Bermasalah'];

    /**
     * Rit milik driver pada satu tanggal, siap ditampilkan di layar utama.
     *
     * Dipakai kalau aplikasi meminta tanggal tertentu. Beranda tidak lagi
     * memakainya — lihat [tugasAktif].
     */
    public function tugasHarian(drivers $driver, string $tanggal)
    {
        return DaftarMuat::with($this->relasiTampilan())
            ->where('driver_id', $driver->id)
            ->whereDate('tanggal_muatan', $tanggal)
            ->orderBy('nomor_rit')
            ->get();
    }

    /**
     * Rit yang sedang jadi tanggung jawab driver, untuk beranda.
     *
     * Yang menentukan sebuah rit muncul di HP sopir adalah **namanya sudah
     * dimasukkan admin**, bukan tanggalnya. Dulu penyaringnya tanggal persis
     * hari ini, dan itu tidak cocok dengan cara kantor bekerja: rit diinput
     * sore untuk besok, jadi sopir yang membuka aplikasi malam hari melihat
     * "belum ada kiriman" padahal tugasnya sudah ditetapkan. Rit susulan yang
     * dibuat siang hari juga jadi ikut benar dengan sendirinya — begitu nama
     * sopir diisi, ritnya muncul pada tarikan layar berikutnya.
     *
     * Dua hal yang membatasi supaya berandanya tidak jadi tumpukan:
     *
     * - Hari ini dan ke depan ikut apa adanya, termasuk yang sudah selesai,
     *   supaya sopir tetap melihat hasil kerjanya hari itu.
     * - Ke belakang hanya yang belum tuntas, sebatas [driver.jendela_rit_hari]
     *   hari. Tanpa batas ini, 6.920 catatan pembukuan lama yang tidak pernah
     *   disentuh sopir ikut terbawa — sebagian sopir sampai 153 rit sekaligus.
     */
    public function tugasAktif(drivers $driver)
    {
        $hariIni = now()->timezone('Asia/Jakarta')->toDateString();
        $batas   = now()->timezone('Asia/Jakarta')
            ->subDays(max(0, (int) config('driver.jendela_rit_hari', 2)))
            ->toDateString();

        return DaftarMuat::with($this->relasiTampilan())
            ->where('driver_id', $driver->id)
            ->whereDate('tanggal_muatan', '>=', $batas)
            ->where(function ($q) use ($hariIni) {
                $q->whereDate('tanggal_muatan', '>=', $hariIni)
                    ->orWhereNull('selesai_at');
            })
            ->orderBy('tanggal_muatan')
            ->orderBy('nomor_rit')
            ->get();
    }

    /**
     * Riwayat rit driver, terbaru lebih dulu. Tanpa `dari` berarti sejak rit pertama.
     *
     * Dipaginasi, bukan diambil sekaligus: driver lama bisa punya ratusan rit,
     * dan menariknya dalam satu permintaan membakar kuota di daerah bersinyal
     * buruk — persoalan yang sama dengan alasan [ringkasan] dihitung di server.
     *
     * Rit yang masih berjalan hari ini sengaja ikut terbawa bila masuk rentang:
     * yang membatasi adalah tanggalnya, bukan statusnya, supaya driver tidak
     * bingung kenapa rit yang baru saja ia kerjakan belum muncul di riwayat.
     */
    public function riwayat(
        drivers $driver,
        ?string $dari,
        string $sampai,
        int $perHalaman,
    ) {
        return DaftarMuat::with($this->relasiTampilan())
            ->where('driver_id', $driver->id)
            ->when($dari, fn ($q) => $q->where('tanggal_muatan', '>=', $dari))
            ->where('tanggal_muatan', '<=', $sampai)
            ->orderByDesc('tanggal_muatan')
            ->orderByDesc('nomor_rit')
            ->paginate($perHalaman);
    }

    /**
     * Relasi yang selalu dibutuhkan TugasResource, termasuk jumlah foto per
     * tujuan. Satu tempat supaya tiap jalur mengembalikan bentuk yang sama.
     */
    public function relasiTampilan(): array
    {
        return [
            'kendaraan',
            'checker',
            'items' => fn ($q) => $q->withCount('bongkarFotos'),
        ];
    }

    /**
     * Ringkasan kerja driver sepanjang satu rentang tanggal.
     *
     * Dihitung di server, bukan dengan menarik seluruh rit ke HP lalu
     * menjumlahkannya di sana: driver bisa punya ratusan rit dalam sebulan, dan
     * mengirim semuanya cuma untuk menampilkan enam angka membakar kuota di
     * daerah bersinyal buruk.
     */
    public function ringkasan(drivers $driver, string $dari, string $sampai): array
    {
        $rit = DaftarMuat::withCount([
            'items',
            'items as items_selesai_count' => fn ($q) => $q->where('status_bongkar', 'Selesai'),
            'items as items_bermasalah_count' => fn ($q) => $q->whereIn('status_bongkar', ['Bermasalah', 'Gagal']),
        ])
            ->where('driver_id', $driver->id)
            ->whereBetween('tanggal_muatan', [$dari, $sampai])
            ->get();

        $selesai = $rit->where('status_perjalanan', 'Selesai');
        $batal   = $rit->where('status_perjalanan', 'Batal');

        return [
            'periode' => [
                'dari'   => $dari,
                'sampai' => $sampai,
            ],
            'rit' => [
                'total'    => $rit->count(),
                'selesai'  => $selesai->count(),
                'berjalan' => $rit->whereIn('status_perjalanan', ['Menunggu', 'Dimuat', 'Berangkat'])->count(),
                'batal'    => $batal->count(),
            ],
            'tujuan' => [
                'total'      => $rit->sum('items_count'),
                'selesai'    => $rit->sum('items_selesai_count'),
                'bermasalah' => $rit->sum('items_bermasalah_count'),
            ],
            // Rit yang dibatalkan tidak pernah diangkut, jadi tidak ikut
            // dihitung sebagai muatan yang pernah dibawa driver.
            'muatan_ton' => round((float) $rit->whereNotIn('status_perjalanan', ['Batal'])->sum('total_muatan_ton'), 2),
            'jenis' => [
                'subsidi'     => $rit->where('jenis_muatan', 'subsidi')->count(),
                'non_subsidi' => $rit->where('jenis_muatan', '!=', 'subsidi')->count(),
            ],
            'wilayah' => $this->sebaranWilayah($driver, $dari, $sampai),
        ];
    }

    /**
     * Sebaran tujuan per kode area, terbanyak lebih dulu.
     *
     * Sebagian tujuan kode areanya memang kosong di data — dikumpulkan jadi
     * satu baris "Tanpa kode" alih-alih dibuang, supaya jumlahnya tetap
     * menjumlah dan driver tidak bingung kenapa angkanya tidak cocok.
     */
    private function sebaranWilayah(drivers $driver, string $dari, string $sampai): array
    {
        return DaftarMuatItem::query()
            ->join('daftar_muats', 'daftar_muats.id', '=', 'daftar_muat_items.daftar_muat_id')
            ->where('daftar_muats.driver_id', $driver->id)
            ->whereBetween('daftar_muats.tanggal_muatan', [$dari, $sampai])
            ->where('daftar_muats.status_perjalanan', '!=', 'Batal')
            ->selectRaw("COALESCE(NULLIF(daftar_muat_items.kode_area, ''), '-') as kode, COUNT(*) as jumlah")
            ->groupBy('kode')
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn ($baris) => [
                'kode'   => $baris->kode === '-' ? null : $baris->kode,
                'jumlah' => (int) $baris->jumlah,
            ])
            ->values()
            ->all();
    }

    /**
     * Menutup satu tujuan dengan laporan driver.
     *
     * Foto dan keterangan dua-duanya wajib, termasuk saat pengirimannya lancar.
     * Foto sudah diunggah lebih dulu lewat endpoint tersendiri supaya bisa
     * diantrikan saat sinyal mati; yang dikirim ke sini tinggal keputusannya.
     */
    public function simpanLaporan(
        drivers $driver,
        int $daftarMuatId,
        int $itemId,
        string $status,
        ?string $catatan = null,
        ?float $lat = null,
        ?float $lng = null,
    ): DaftarMuatItem {
        return DB::transaction(function () use ($driver, $daftarMuatId, $itemId, $status, $catatan, $lat, $lng) {
            // Dikunci supaya dua tap cepat dari HP tidak menutup tujuan dua kali.
            $muat = DaftarMuat::whereKey($daftarMuatId)->lockForUpdate()->firstOrFail();

            $this->pastikanMilikDriver($muat, $driver);

            if ($muat->statusPerjalanan() === 'Batal') {
                throw new TransisiStatusException(
                    'Rit ini dibatalkan admin. Hubungi kantor.',
                    'RIT_DIBATALKAN'
                );
            }

            if (!in_array($status, self::JALAN_KELUAR, true)) {
                throw new TransisiStatusException(
                    'Laporan tujuan hanya bisa Selesai atau Bermasalah.',
                    'TRANSISI_TIDAK_SAH'
                );
            }

            $item = DaftarMuatItem::whereKey($itemId)
                ->where('daftar_muat_id', $muat->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->tuntas()) {
                throw new TransisiStatusException(
                    "Tujuan ini sudah ditandai \"{$item->status_bongkar}\".",
                    'TUJUAN_SUDAH_TUNTAS'
                );
            }

            if ($item->bongkarFotos()->count() === 0) {
                throw new TransisiStatusException(
                    'Ambil dulu minimal satu foto sebelum mengirim laporan.',
                    'FOTO_WAJIB'
                );
            }

            if (blank($catatan)) {
                throw new TransisiStatusException(
                    'Keterangan wajib diisi.',
                    'CATATAN_WAJIB'
                );
            }

            $item->status_bongkar     = $status;
            $item->catatan_driver     = $catatan;
            $item->bongkar_selesai_at = now();

            // Jam tiba tidak lagi ditekan terpisah. Diisi di sini supaya rekap
            // admin tetap punya penanda kapan truk sampai di tujuan itu.
            $item->tiba_at ??= now();

            $item->save();

            $this->catat(
                $muat,
                "Tujuan {$item->urutan} {$status}",
                trim("{$item->lokasi_pengiriman}. {$catatan}"),
                $driver,
                $lat,
                $lng,
            );

            // Rit ikut maju sendiri: jalan begitu laporan pertama masuk, tutup
            // begitu tujuan terakhir dilaporkan.
            if ($statusRit = $muat->selaraskanStatus()) {
                $this->catat($muat, $statusRit, $this->keteranganRit($statusRit), $driver, $lat, $lng);
            }

            return $item;
        });
    }

    private function pastikanMilikDriver(DaftarMuat $muat, drivers $driver): void
    {
        if ($muat->driver_id !== $driver->id) {
            throw new TransisiStatusException(
                'Rit ini bukan tugas Anda.',
                'BUKAN_TUGAS_ANDA'
            );
        }
    }

    private function keteranganRit(string $status): string
    {
        return match ($status) {
            'Berangkat' => 'Rit mulai dikerjakan, laporan pertama masuk',
            'Selesai'   => 'Semua tujuan sudah dilaporkan',
            default     => '',
        };
    }

    private function catat(
        DaftarMuat $muat,
        string $status,
        string $keterangan,
        drivers $driver,
        ?float $lat,
        ?float $lng,
    ): void {
        DaftarMuatLog::create([
            'daftar_muat_id' => $muat->id,
            'status'         => $status,
            'keterangan'     => $keterangan ?: null,
            // user_id sengaja dikosongkan: kolom itu menunjuk tabel users,
            // sedangkan pelakunya driver.
            'user_id'        => null,
            'driver_id'      => $driver->id,
            'aktor'          => 'driver',
            'latitude'       => $lat,
            'longitude'      => $lng,
            'created_at'     => now(),
        ]);
    }
}
