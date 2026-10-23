<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AbsenRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Services\AttendanceService;
use App\Services\IzinService;
use App\Models\Presensi;
use App\Models\KunjunganToko;
use App\Http\Resources\Api\SalesResource;
use App\Http\Resources\Api\PresensiResource;
use App\Http\Resources\Api\IzinResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $service,
        protected IzinService $izinService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $sales = \App\Models\Sales::where('nik', $request->nik)->first();

        if (!$sales || !Hash::check($request->password, $sales->password)) {
            return response()->json([
                'success' => false,
                'message' => 'NIK atau Password salah',
            ], 401);
        }

        if (!$sales->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif. Hubungi admin.',
            ], 403);
        }

        $sales->device_id = $request->device_id;
        if ($request->fcm_token) {
            $sales->fcm_token = $request->fcm_token;
        }
        $sales->last_login_at = now();
        $sales->save();

        $token = $sales->createToken('sales_auth_token')->plainTextToken;

        return response()->json([
            'success'      => true,
            'message'      => 'Login Berhasil',
            'access_token' => $token,
            'user'         => new SalesResource($sales->refresh()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json(['success' => true, 'message' => 'Logout berhasil']);
        } catch (\Exception $e) {
            Log::error('Logout error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal logout'], 500);
        }
    }

    public function absenMasuk(AbsenRequest $request): JsonResponse
    {
        try {
            $sales = $request->user();

            $cekIzin = $this->izinService->cekBolehAbsenMasuk($sales->id);
            if (!$cekIzin['boleh']) {
                return response()->json([
                    'success'    => false,
                    'message'    => $cekIzin['alasan'],
                    'izin_aktif' => $cekIzin['izin'] ? new IzinResource($cekIzin['izin']) : null,
                    'kode'       => 'IZIN_AKTIF',
                ], 403);
            }

            $jamServer = Carbon::now('Asia/Jakarta')->format('H:i:s');
            $status    = $this->izinService->tentukanStatusAbsen($sales->id, $jamServer);

            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;
            $acc = $request->accuracy ? (float) $request->accuracy : null;

            $data = [
                'location_masuk'      => $request->latitude . ',' . $request->longitude,
                'accuracy_masuk'      => $acc,
                'is_suspicious_masuk' => !$this->isInsideIndonesia($lat, $lng),
                'jam_perangkat_masuk' => $request->jam_perangkat ?? null,
                'keterangan'          => $request->keterangan ?? null,
                'status_override'     => $status,
            ];

            $presensi = $this->service->handleMasuk($sales->id, $data, $request->file('foto_masuk'));

            $message = match($status) {
                'terlambat_izin' => 'Absen masuk berhasil (Terlambat - Ada Izin)',
                'terlambat'      => 'Absen masuk berhasil (Terlambat)',
                default          => 'Absen masuk berhasil',
            };

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => new PresensiResource($presensi),
            ]);
        } catch (\Exception $e) {
            Log::error('Absen masuk error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function absenPulang(AbsenRequest $request): JsonResponse
    {
        try {
            $sales = $request->user();
            $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

            $presensi = Presensi::where('sales_id', $sales->id)
                ->where('tanggal', $today)
                ->first();

            if (!$presensi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum absen masuk',
                ], 400);
            }

            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;
            $acc = $request->accuracy ? (float) $request->accuracy : null;

            $data = [
                'location_pulang'       => $request->latitude . ',' . $request->longitude,
                'accuracy_pulang'       => $acc,
                'is_suspicious_pulang'  => !$this->isInsideIndonesia($lat, $lng),
                'jam_perangkat_pulang'  => $request->jam_perangkat ?? null,
                'pulang_cepat_izin'     => $this->izinService->bolehPulangCepat($sales->id),
            ];

            $updated = $this->service->handlePulang($presensi, $data, $request->file('foto_pulang'));

            return response()->json([
                'success' => true,
                'message' => $data['pulang_cepat_izin']
                    ? 'Absen pulang berhasil (Pulang Cepat - Ada Izin)'
                    : 'Absen pulang berhasil',
                'data'    => new PresensiResource($updated),
            ]);
        } catch (\Exception $e) {
            Log::error('Absen pulang error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function presensiHariIni(Request $request): JsonResponse
    {
        $sales = $request->user();
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        $presensi        = Presensi::where('sales_id', $sales->id)->where('tanggal', $today)->first();
        $jumlahKunjungan = KunjunganToko::where('sales_id', $sales->id)
            ->whereDate('created_at', $today)
            ->count();

        $sudahMasuk  = $presensi !== null;
        $sudahPulang = $presensi?->jam_pulang !== null;
        $cekIzin     = $this->izinService->cekBolehAbsenMasuk($sales->id);
        $izinAktif   = $this->izinService->getIzinAktif($sales->id);
        $target      = $sales->target_kunjungan ?? 3;

        return response()->json([
            'success'    => true,
            'message'    => $presensi ? 'Data ditemukan' : 'Belum presensi hari ini',
            'data'       => $presensi ? new PresensiResource($presensi) : null,
            'izin_aktif' => $izinAktif ? new IzinResource($izinAktif) : null,
            'progress'   => [
                'sudah_masuk'       => $sudahMasuk,
                'sudah_pulang'      => $sudahPulang,
                'jumlah_kunjungan'  => $jumlahKunjungan,
                'minimal_kunjungan' => $target,
                'bisa_masuk'        => !$sudahMasuk && $cekIzin['boleh'],
                'bisa_kunjungan'    => $sudahMasuk && !$sudahPulang,
                'bisa_pulang'       => $sudahMasuk && !$sudahPulang && KunjunganToko::targetTerpenuhi($sales->id, $today),
                'sedang_izin'       => !$cekIzin['boleh'],
                'keterangan_izin'   => $cekIzin['alasan'],
                'jenis_izin_aktif'  => $izinAktif?->jenis_izin,
            ],
        ]);
    }

    public function riwayat(Request $request): JsonResponse
    {
        $sales = $request->user();
        $query = Presensi::where('sales_id', $sales->id)->orderBy('tanggal', 'desc');

        if ($request->tanggal_mulai) $query->whereDate('tanggal', '>=', $request->tanggal_mulai);
        if ($request->tanggal_akhir) $query->whereDate('tanggal', '<=', $request->tanggal_akhir);

        $perPage = min((int) ($request->per_page ?? 30), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => PresensiResource::collection($paginated->items()),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
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
}
