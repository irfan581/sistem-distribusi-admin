<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrackingController extends Controller
{
    // ── Flutter: Sales kirim koordinat GPS (untuk masa depan) ──
    public function kirimLokasi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
            'akurasi'     => 'nullable|numeric|min:0',
            'recorded_at' => 'nullable|date',
        ]);

        $jam        = now()->timezone('Asia/Jakarta');
        $jamMulai   = $jam->copy()->setTime(7, 0);
        $jamSelesai = $jam->copy()->setTime(21, 0);

        if ($jam->lt($jamMulai) || $jam->gt($jamSelesai)) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking hanya aktif pukul 07:00 sampai 21:00 WIB.',
            ], 422);
        }

        SalesLocation::create([
            'user_id'     => $request->user()->id,
            'lat'         => $data['lat'],
            'lng'         => $data['lng'],
            'akurasi'     => $data['akurasi'] ?? null,
            'recorded_at' => isset($data['recorded_at']) ? Carbon::parse($data['recorded_at']) : now(),
            'created_at'  => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Lokasi berhasil dicatat.']);
    }

    // ── Admin Vue: Posisi terakhir semua sales ────────────────
    // Diambil dari kunjungan toko (lat/lng) + absen masuk (location_masuk)
    // Tidak perlu update Flutter — pakai data yang sudah ada
    public function live(): JsonResponse
    {
        $tanggalHariIni = now()->timezone('Asia/Jakarta')->toDateString();

        // 1. Ambil kunjungan terakhir tiap sales hari ini
        $kunjungan = DB::table('kunjungan_tokos as k')
            ->join('sales as s', 's.id', '=', 'k.sales_id')
            ->whereDate('k.created_at', $tanggalHariIni)
            ->whereNotNull('k.lat')
            ->whereNotNull('k.lng')
            ->select(
                'k.sales_id as user_id',
                's.nama as nama',
                's.foto_profil as avatar',
                'k.lat',
                'k.lng',
                'k.accuracy as akurasi',
                'k.nama_toko as lokasi_terakhir',
                'k.created_at as recorded_at',
                DB::raw("'kunjungan' as sumber")
            )
            ->orderByDesc('k.created_at')
            ->get()
            ->unique('user_id');

        // 2. Ambil sales yang absen hari ini tapi belum ada di kunjungan
        $salesDiKunjungan = $kunjungan->pluck('user_id')->toArray();

        $absen = DB::table('presensis as p')
            ->join('sales as s', 's.id', '=', 'p.sales_id')
            ->where('p.tanggal', $tanggalHariIni)
            ->whereNotNull('p.location_masuk')
            ->whereNotIn('p.sales_id', $salesDiKunjungan)
            ->select(
                'p.sales_id as user_id',
                's.nama as nama',
                's.foto_profil as avatar',
                'p.location_masuk',
                'p.accuracy_masuk as akurasi',
                'p.jam_masuk as recorded_at',
                DB::raw("'absen' as sumber")
            )
            ->get()
            ->map(function ($row) {
                // Parse "lat,lng" dari location_masuk
                $parts = explode(',', $row->location_masuk);
                $row->lat = isset($parts[0]) ? (float) $parts[0] : null;
                $row->lng = isset($parts[1]) ? (float) $parts[1] : null;
                $row->lokasi_terakhir = 'Lokasi Absen Masuk';
                return $row;
            })
            ->filter(fn($r) => $r->lat && $r->lng);

        // 3. Gabung & format
        $hasil = $kunjungan->concat($absen)->values()->map(function ($loc) {
            $recordedAt = Carbon::parse($loc->recorded_at)->timezone('Asia/Jakarta');
            return [
                'user_id'         => $loc->user_id,
                'nama'            => $loc->nama ?? '-',
                'avatar'          => $loc->avatar ?? null,
                'lat'             => (float) $loc->lat,
                'lng'             => (float) $loc->lng,
                'akurasi'         => $loc->akurasi ? round($loc->akurasi) : null,
                'lokasi_terakhir' => $loc->lokasi_terakhir ?? '-',
                'sumber'          => $loc->sumber,
                'recorded_at'     => $recordedAt->format('H:i'),
                'menit_lalu'      => $recordedAt->diffInMinutes(now()),
            ];
        });

        return response()->json(['success' => true, 'data' => $hasil]);
    }

    // ── Admin Vue: Rute sales dari titik-titik kunjungan ─────
    public function rute(Request $request, int $userId): JsonResponse
    {
        $request->validate(['tanggal' => 'nullable|date']);

        $tanggal = $request->filled('tanggal')
            ? Carbon::parse($request->tanggal)->toDateString()
            : now()->timezone('Asia/Jakarta')->toDateString();

        // Ambil semua kunjungan sales hari itu urut waktu (userId = sales.id)
        $titikKunjungan = DB::table('kunjungan_tokos')
            ->where('sales_id', $userId)
            ->whereDate('created_at', $tanggal)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderBy('created_at')
            ->get(['lat', 'lng', 'accuracy', 'nama_toko', 'created_at'])
            ->map(fn($k) => [
                'lat'         => (float) $k->lat,
                'lng'         => (float) $k->lng,
                'akurasi'     => $k->accuracy ? round($k->accuracy) : null,
                'label'       => $k->nama_toko,
                'recorded_at' => Carbon::parse($k->created_at)->timezone('Asia/Jakarta')->format('H:i'),
                'sumber'      => 'kunjungan',
            ]);

        // Tambahkan titik absen masuk di awal jika ada
        $absen = DB::table('presensis')
            ->where('sales_id', $userId)
            ->where('tanggal', $tanggal)
            ->whereNotNull('location_masuk')
            ->first(['location_masuk', 'accuracy_masuk', 'jam_masuk']);

        $titikAbsen = collect();
        if ($absen) {
            $parts = explode(',', $absen->location_masuk);
            $lat   = isset($parts[0]) ? (float) $parts[0] : null;
            $lng   = isset($parts[1]) ? (float) $parts[1] : null;
            if ($lat && $lng) {
                $titikAbsen = collect([[
                    'lat'         => $lat,
                    'lng'         => $lng,
                    'akurasi'     => $absen->accuracy_masuk ? round($absen->accuracy_masuk) : null,
                    'label'       => 'Absen Masuk',
                    'recorded_at' => Carbon::parse($tanggal . ' ' . $absen->jam_masuk)->timezone('Asia/Jakarta')->format('H:i'),
                    'sumber'      => 'absen',
                ]]);
            }
        }

        $semua = $titikAbsen->concat($titikKunjungan)->values();

        return response()->json([
            'success' => true,
            'user_id' => $userId,
            'tanggal' => $tanggal,
            'total'   => $semua->count(),
            'data'    => $semua,
        ]);
    }

    // ── Admin Vue: List sales yang aktif hari ini ─────────────
    public function salesAktif(): JsonResponse
    {
        $tanggalHariIni = now()->timezone('Asia/Jakarta')->toDateString();

        // Sales yang sudah kunjungan atau absen hari ini
        $dariKunjungan = DB::table('kunjungan_tokos as k')
            ->join('sales as s', 's.id', '=', 'k.sales_id')
            ->whereDate('k.created_at', $tanggalHariIni)
            ->whereNotNull('k.lat')
            ->select('k.sales_id as id', 's.nama as nama', 's.foto_profil as avatar')
            ->distinct();

        $dariAbsen = DB::table('presensis as p')
            ->join('sales as s', 's.id', '=', 'p.sales_id')
            ->where('p.tanggal', $tanggalHariIni)
            ->whereNotNull('p.location_masuk')
            ->select('p.sales_id as id', 's.nama as nama', 's.foto_profil as avatar')
            ->distinct();

        $sales = $dariKunjungan->union($dariAbsen)->get()->unique('id')->values();

        return response()->json(['success' => true, 'data' => $sales]);
    }
}
