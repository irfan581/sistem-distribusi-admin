<?php

namespace App\Services;

use App\Jobs\ProcessKunjungan;
use App\Models\KunjunganToko;
use App\Models\Presensi;
use App\Models\Sales;
use App\Models\Toko;
use App\Services\GeocodingService;
use App\Traits\UploadGambar;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class KunjunganService
{
    use UploadGambar;

    public function __construct(private GeocodingService $geocoding) {}

    public function storeKunjungan(int $salesId, array $data, $file): KunjunganToko
    {
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        // Cek wajib absen masuk dulu
        $sudahMasuk = Presensi::where('sales_id', $salesId)
            ->where('tanggal', $today)
            ->exists();

        if (!$sudahMasuk) {
            throw new \Exception('Anda harus absen masuk terlebih dahulu.');
        }

        // Cek sudah absen pulang
        $sudahPulang = Presensi::where('sales_id', $salesId)
            ->where('tanggal', $today)
            ->whereNotNull('jam_pulang')
            ->exists();

        if ($sudahPulang) {
            throw new \Exception('Anda sudah absen pulang. Kunjungan tidak dapat ditambah.');
        }

        // Tentukan toko_id dan nama_toko
        $tokoId   = null;
        $namaToko = null;
        $toko     = null;

        if (!empty($data['toko_id'])) {
            // Mode: pilih dari master toko
            $toko     = Toko::findOrFail($data['toko_id']);
            $tokoId   = $toko->id;
            $namaToko = $toko->nama_kios;

            // Cek duplikasi: satu toko hanya bisa dikunjungi 1x per hari
            $isDuplicate = KunjunganToko::where('sales_id', $salesId)
                ->where('toko_id', $tokoId)
                ->whereDate('created_at', $today)
                ->exists();

            if ($isDuplicate) {
                throw new \Exception('Anda sudah mengunjungi toko "' . $namaToko . '" hari ini.');
            }

            // Cek cooldown 5 hari — skip jika bypass_cooldown=true dan sales adalah SPV,
            // atau kalau toko-nya berjenis kantor (boleh dikunjungi tiap hari kerja)
            $bypassCooldown = (!empty($data['bypass_cooldown']) && $this->canBypassCooldown($salesId))
                || $toko->isKantor();

            if (!$bypassCooldown) {
                $cooldown = $this->cekCooldown($salesId, $tokoId);
                if ($cooldown['cooldown']) {
                    throw new \Exception(
                        'Anda baru mengunjungi toko "' . $namaToko . '" ' .
                        $cooldown['hari_sejak'] . ' hari yang lalu. ' .
                        'Bisa kunjungi lagi setelah ' . $cooldown['bisa_lagi'] . '. ' .
                        'Sisa cooldown: ' . $cooldown['sisa_hari'] . ' hari.'
                    );
                }
            }
        } else {
            // Mode: ketik manual (toko baru / belum ada di master)
            $namaToko = trim($data['nama_toko']);

            // Cek duplikasi berdasarkan nama toko manual per hari
            $isDuplicate = KunjunganToko::where('sales_id', $salesId)
                ->whereNull('toko_id')
                ->where('nama_toko', $namaToko)
                ->whereDate('created_at', $today)
                ->exists();

            if ($isDuplicate) {
                throw new \Exception('Anda sudah mengunjungi toko "' . $namaToko . '" hari ini.');
            }
        }

        $path = $this->uploadCompressed($file, 'temp-kunjungan');

        [$latVal, $lngVal] = $this->parseLatLng($data['location']);
        $accuracy      = isset($data['accuracy']) ? (float) $data['accuracy'] : null;
        $isMock        = (bool) ($data['is_mock_location'] ?? false);
        $isZero        = ($data['location'] === '0,0');
        $isOutOfBounds = $latVal !== null && $lngVal !== null
                      && !$this->isInsideIndonesia($latVal, $lngVal);
        $isPoorAccuracy = $accuracy !== null && $accuracy > 200;

        $isSuspicious = $isMock || $isZero || $isOutOfBounds;

        $reasons = [];
        if ($isMock)        $reasons[] = 'Terdeteksi Fake GPS';
        if ($isZero)        $reasons[] = 'Koordinat tidak valid (0,0)';
        if ($isOutOfBounds) $reasons[] = 'Koordinat di luar wilayah Indonesia';
        if ($isPoorAccuracy) $reasons[] = "Akurasi GPS buruk ({$accuracy}m)";

        $kunjungan = KunjunganToko::create([
            'sales_id'          => $salesId,
            'toko_id'           => $tokoId,
            'nama_toko'         => $namaToko,
            'location'          => $data['location'],
            'lat'               => $latVal,
            'lng'               => $lngVal,
            'alamat'            => $this->geocoding->reverseGeocode($latVal, $lngVal),
            'accuracy'          => $accuracy,
            'keterangan'        => $data['keterangan'] ?? null,
            'foto_kunjungan'    => $path,
            'is_suspicious'     => $isSuspicious,
            'suspicious_reason' => !empty($reasons) ? implode('; ', $reasons) : null,
            // Ditulis eksplisit: default kolom tidak terbawa ke model yang baru dibuat,
            // sehingga respons simpan akan mengirim perlu = false.
            'perlu_tanggapan'   => true,
        ]);

        // Lokasi lolos pemeriksaan (bukan fake GPS, bukan 0,0, di dalam Indonesia):
        // diterima otomatis. Yang mencurigakan menunggu admin.
        if (! $isSuspicious) {
            $kunjungan->terimaOtomatis();
        }

        // Kunjungan yang memilih dari Data Toko ikut memperbarui titik tokonya
        $toko?->isiLokasiDari($kunjungan);

        ProcessKunjungan::dispatch($kunjungan->id, $path);

        return $kunjungan;
    }

    public function getProgressHariIni(int $salesId): array
    {
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        $totalKunjungan = KunjunganToko::where('sales_id', $salesId)
            ->whereDate('created_at', $today)
            ->count();

        $target = Sales::find($salesId)?->target_kunjungan ?? 3;

        return [
            'jumlah_kunjungan'  => $totalKunjungan,
            'minimal_kunjungan' => $target,
            'bisa_pulang'       => KunjunganToko::targetTerpenuhi($salesId, $today),
        ];
    }

    private function parseLatLng(string $location): array
    {
        $parts = explode(',', $location, 2);
        $lat   = isset($parts[0]) ? (float) trim($parts[0]) : null;
        $lng   = isset($parts[1]) ? (float) trim($parts[1]) : null;
        return [$lat, $lng];
    }

    /**
     * Validasi koordinat berada di dalam wilayah Indonesia.
     * Bounding box: lat -11 s/d 6, lng 95 s/d 141
     */
    private function isInsideIndonesia(float $lat, float $lng): bool
    {
        return $lat >= -11.0 && $lat <= 6.0
            && $lng >= 95.0  && $lng <= 141.0;
    }

    public function cekCooldown(int $salesId, int $tokoId): array
    {
        $cooldownHari = 5;

        $last = KunjunganToko::where('sales_id', $salesId)
            ->where('toko_id', $tokoId)
            ->whereNull('archived_at')
            ->latest('created_at')
            ->first();

        if (!$last) {
            return ['cooldown' => false, 'sisa_hari' => 0, 'hari_sejak' => 0, 'bisa_lagi' => null, 'pesan' => null];
        }

        $now       = Carbon::now('Asia/Jakarta');
        $lastVisit = Carbon::parse($last->created_at)->setTimezone('Asia/Jakarta');
        $hariSejak = (int) $lastVisit->diffInDays($now);

        if ($hariSejak >= $cooldownHari) {
            return ['cooldown' => false, 'sisa_hari' => 0, 'hari_sejak' => $hariSejak, 'bisa_lagi' => null, 'pesan' => null];
        }

        $sisaHari  = $cooldownHari - $hariSejak;
        $bisaLagi  = $lastVisit->copy()->addDays($cooldownHari)->locale('id')->isoFormat('D MMM YYYY');

        return [
            'cooldown'   => true,
            'sisa_hari'  => $sisaHari,
            'hari_sejak' => $hariSejak,
            'bisa_lagi'  => $bisaLagi,
            'pesan'      => "Toko ini baru dikunjungi {$hariSejak} hari lalu. Bisa kunjungi lagi: {$bisaLagi} (sisa {$sisaHari} hari).",
        ];
    }

    private function canBypassCooldown(int $salesId): bool
    {
        $sales = Sales::find($salesId);
        return $sales?->is_spv === true;
    }

    public function getRiwayat(
        int     $salesId,
        ?string $tanggalMulai = null,
        ?string $tanggalAkhir = null,
    ): Collection {
        return KunjunganToko::where('sales_id', $salesId)
            ->select([
                'id', 'sales_id', 'toko_id', 'nama_toko', 'location',
                'foto_kunjungan', 'keterangan',
                'is_suspicious', 'suspicious_reason', 'created_at',
                'perlu_tanggapan', 'tanggapan_status', 'tanggapan_catatan', 'ditanggapi_oleh', 'ditanggapi_pada',
            ])
            ->with('toko:id,nama_kios,wilayah,sales_id')
            ->when($tanggalMulai, fn($q) => $q->whereDate('created_at', '>=', $tanggalMulai))
            ->when($tanggalAkhir, fn($q) => $q->whereDate('created_at', '<=', $tanggalAkhir))
            ->when(!$tanggalMulai && !$tanggalAkhir,
                fn($q) => $q->where('created_at', '>=', now()->subDays(90))
            )
            ->latest()
            ->limit(180)
            ->get();
    }
}
