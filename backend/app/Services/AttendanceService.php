<?php

namespace App\Services;

use App\Models\Presensi;
use App\Models\KunjunganToko;
use App\Models\Sales;
use App\Models\SalesVisitTarget;
use App\Jobs\ProcessPresensi;
use App\Services\GeocodingService;
use App\Traits\UploadGambar;
use Carbon\Carbon;

class AttendanceService
{
    use UploadGambar;

    public function __construct(private GeocodingService $geocoding) {}

    public function handleMasuk(int $salesId, array $data, $file): Presensi
    {
        $now = Carbon::now('Asia/Jakarta');

        if (!$file) {
            throw new \Exception('File foto_masuk diperlukan.');
        }

        if (Presensi::where('sales_id', $salesId)
            ->where('tanggal', $now->format('Y-m-d'))
            ->exists()
        ) {
            throw new \Exception('Anda sudah absen masuk hari ini.');
        }

        $path = $this->uploadCompressed($file, 'temp-absen');

        $status = $data['status_override'] ?? $this->_hitungStatus($now);

        [$lat, $lng] = $this->_parseLatLng($data['location_masuk']);

        $presensi = Presensi::create([
            'sales_id'             => $salesId,
            'tanggal'              => $now->format('Y-m-d'),
            'jam_masuk'            => $now->format('H:i:s'),
            'status'               => $status,
            'jam_perangkat_masuk'  => $data['jam_perangkat_masuk'],
            'foto_masuk'           => $path,
            'location_masuk'       => $data['location_masuk'],
            'alamat_masuk'         => $this->geocoding->reverseGeocode($lat, $lng),
            'accuracy_masuk'       => $data['accuracy_masuk'] ?? null,
            'is_suspicious_masuk'  => $data['is_suspicious_masuk'] ?? false,
            'keterangan'           => $data['keterangan'] ?? null,
        ]);

        ProcessPresensi::dispatch(['foto_masuk' => $path], $presensi->id);

        return $presensi;
    }

    public function handlePulang(Presensi $presensi, array $data, $file): Presensi
    {
        $now = Carbon::now('Asia/Jakarta');

        if (!$file) {
            throw new \Exception('File foto_pulang diperlukan.');
        }

        $pulangCepatIzin = $data['pulang_cepat_izin'] ?? false;

        if (!$pulangCepatIzin && !KunjunganToko::targetTerpenuhi($presensi->sales_id)) {
            $jumlahKunjungan = KunjunganToko::where('sales_id', $presensi->sales_id)
                ->whereDate('created_at', Carbon::today('Asia/Jakarta'))
                ->count();

            $target = SalesVisitTarget::getActiveTargetForSales($presensi->sales_id);

            throw new \Exception("Minimal {$target} kunjungan toko (Baru {$jumlahKunjungan}).");
        }

        $path = $this->uploadCompressed($file, 'temp-absen');

        [$lat, $lng] = $this->_parseLatLng($data['location_pulang']);

        $presensi->update([
            'jam_pulang'            => $now->format('H:i:s'),
            'jam_perangkat_pulang'  => $data['jam_perangkat_pulang'],
            'foto_pulang'           => $path,
            'location_pulang'       => $data['location_pulang'],
            'alamat_pulang'         => $this->geocoding->reverseGeocode($lat, $lng),
            'accuracy_pulang'       => $data['accuracy_pulang'] ?? null,
            'is_suspicious_pulang'  => $data['is_suspicious_pulang'] ?? false,
        ]);

        // Auto-checkout semua kunjungan hari ini yang belum checkout
        KunjunganToko::where('sales_id', $presensi->sales_id)
            ->whereDate('created_at', $now->toDateString())
            ->whereNull('checkout_at')
            ->update(['checkout_at' => $now]);

        ProcessPresensi::dispatch(['foto_pulang' => $path], $presensi->id);

        return $presensi->fresh();
    }

    private function _parseLatLng(string $location): array
    {
        $parts = explode(',', $location, 2);
        $lat   = isset($parts[0]) ? (float) trim($parts[0]) : null;
        $lng   = isset($parts[1]) ? (float) trim($parts[1]) : null;
        return [$lat, $lng];
    }

    private function _hitungStatus(Carbon $now): string
    {
        $batasJam = config('absensi.batas_jam', env('ABSENSI_BATAS_JAM', '08:00'));
        try {
            $batas = Carbon::createFromFormat('H:i', $batasJam, 'Asia/Jakarta');
            return $now->gt($batas) ? 'terlambat' : 'tepat_waktu';
        } catch (\Exception $e) {
            return $now->hour >= 8 ? 'terlambat' : 'tepat_waktu';
        }
    }
}