<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DaftarMuatResource;
use App\Http\Resources\LaporanDriverResource;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\DriverIzin;
use App\Services\DaftarMuatService;
use App\Services\TujuanAlamatResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DaftarMuatController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DaftarMuatService $daftarMuatService,
        protected TujuanAlamatResolver $alamat,
    ) {}

    /**
     * Tujuan non-subsidi wajib dipilih dari master toko (toko_id) — bukan
     * diketik bebas — supaya alamat & koordinat bisa diambil otomatis dari
     * kunjungan sales. Subsidi tetap teks bebas: tujuannya kelompok tani,
     * bukan toko, jadi tidak ada datanya di Kunjungan Sales.
     *
     * Wajib-tidaknya toko_id ditentukan dari jenis_muatan level rencana
     * (bukan per item) — form selalu mengirim satu jenis_muatan untuk
     * seluruh rit, item tidak pernah override sendiri.
     */
    private function itemRules(Request $request): array
    {
        $jenisRencana = $request->input('jenis_muatan');

        return [
            'total_muatan_ton'          => 'required|numeric|min:0.01',
            'items'                     => 'required|array|min:1',
            'items.*.lokasi_pengiriman' => 'required|string|max:255',
            'items.*.jenis_muatan'      => 'nullable|in:subsidi,non_subsidi',
            'items.*.kode_area'         => 'nullable|string|max:20',
            'items.*.catatan_admin'     => 'nullable|string|max:255',
            'items.*.alamat'            => 'nullable|string|max:255',
            'items.*.toko_id'           => [
                $jenisRencana === 'non_subsidi' ? 'required' : 'nullable',
                'integer',
                'exists:tokos,id',
            ],
        ];
    }

    private function pastikanBolehJenis(Request $request, ?string $jenis): void
    {
        abort_unless(
            in_array($jenis, $request->user()->jenisMuatanDiizinkan(), true),
            403,
            'Anda tidak memegang muatan jenis ini.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $query = DaftarMuat::bolehDipegang($request->user())->with(['kendaraan', 'driver', 'items']);

        if ($request->filled('search')) {
            $query->whereHas('items', fn($q) => $q
                ->where('lokasi_pengiriman', 'like', "%{$request->search}%")
            );
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal_muatan', $request->tanggal);
        }

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('tanggal_muatan', '>=', $request->tanggal_dari);
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('tanggal_muatan', '<=', $request->tanggal_sampai);
        }

        if ($request->filled('jenis_muatan')) {
            $query->where('jenis_muatan', $request->jenis_muatan);
        }

        if ($request->input('tanggapan') === 'belum') {
            $query->whereHas('items', fn ($q) => $q->belumDitanggapi());
        }

        if ($request->boolean('belum_driver')) {
            $query->whereNull('driver_id');
        } elseif ($request->boolean('sudah_driver')) {
            $query->whereNotNull('driver_id');
        }

        $query->orderByDesc('tanggal_muatan')->orderByDesc('id');

        $perPage = min((int) ($request->per_page ?? 20), 100);
        $paginated = $query->paginate($perPage);

        foreach ($paginated->items() as $muat) {
            $this->alamat->resolveBanyak($muat->items);
        }

        return response()->json([
            'success' => true,
            'data'    => DaftarMuatResource::collection($paginated->items()),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $muat = DaftarMuat::bolehDipegang($request->user())->with(['kendaraan', 'driver', 'items', 'logs.user'])->findOrFail($id);
        $this->alamat->resolveBanyak($muat->items);
        return response()->json(['success' => true, 'data' => new DaftarMuatResource($muat)]);
    }

    /**
     * Hasil kerja driver di lapangan untuk satu rit: status tiap tujuan, jam
     * bongkar, keterangan, dan seluruh foto buktinya.
     *
     * Dipisah dari show() karena fotonya berat dan cuma dibutuhkan saat admin
     * sengaja membuka laporan — bukan tiap kali daftar rit dimuat.
     */
    public function laporanDriver(Request $request, int $id): JsonResponse
    {
        $muat = DaftarMuat::bolehDipegang($request->user())->with([
            'driver',
            'items' => fn ($q) => $q->with([
                'bongkarFotos' => fn ($f) => $f->orderBy('id'),
                'penanggap:id,name',
            ]),
        ])->findOrFail($id);

        $this->alamat->resolveBanyak($muat->items);

        return response()->json([
            'success' => true,
            'data'    => new LaporanDriverResource($muat),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DaftarMuat::class);

        $request->validate(array_merge([
            'kendaraan_id'   => 'nullable|exists:kendaraans,id',
            'driver_id'      => 'nullable|exists:drivers,id',
            'tanggal_muatan' => 'required|date',
            'nomor_rit'      => 'nullable|integer|min:1|max:99',
            'jenis_muatan'   => 'required|in:subsidi,non_subsidi',
        ], $this->itemRules($request)));
        $this->pastikanBolehJenis($request, $request->jenis_muatan);

        try {
            $muat = $this->daftarMuatService->prosesPendaftaranMuat($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Jadwal muat berhasil disimpan.',
                'data'    => new DaftarMuatResource($muat->load(['kendaraan', 'driver', 'items'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $muat = DaftarMuat::bolehDipegang($request->user())->findOrFail($id);
        $this->authorize('update', $muat);

        $request->validate(array_merge([
            'kendaraan_id'   => 'nullable|exists:kendaraans,id',
            'driver_id'      => 'nullable|exists:drivers,id',
            'tanggal_muatan' => 'required|date',
            'nomor_rit'      => 'nullable|integer|min:1|max:99',
            'jenis_muatan'   => 'required|in:subsidi,non_subsidi',
        ], $this->itemRules($request)));
        $this->pastikanBolehJenis($request, $request->jenis_muatan);

        try {
            $muat = $this->daftarMuatService->updateMuat($muat, $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Jadwal muat berhasil diperbarui.',
                'data'    => new DaftarMuatResource($muat->load(['kendaraan', 'driver', 'items'])),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $muat = DaftarMuat::bolehDipegang($request->user())->findOrFail($id);
        $this->authorize('delete', $muat);

        // Hapusnya permanen dan ikut membuang foto bukti, jadi rit yang sudah dikerjakan sopir harus tetap jadi riwayat.
        $sudahDikerjakan = $muat->sudahJalan()
            || $muat->items()->where(fn ($q) => $q->whereIn('status_bongkar', \App\Models\DaftarMuatItem::STATUS_TUNTAS)->orHas('bongkarFotos'))->exists();
        if ($sudahDikerjakan) {
            return response()->json([
                'success' => false,
                'message' => 'Rit ini sudah dikerjakan sopir, jadi tidak bisa dihapus agar riwayatnya tetap tersimpan.',
            ], 422);
        }

        $muat->delete();
        return response()->json(['success' => true, 'message' => 'Jadwal muat berhasil dibatalkan.']);
    }

    public function logs(Request $request, int $id): JsonResponse
    {
        $muat = DaftarMuat::bolehDipegang($request->user())->with(['logs.user'])->findOrFail($id);
        $logs = $muat->logs->map(fn($log) => [
            'id'         => $log->id,
            'status'     => $log->status,
            'keterangan' => $log->keterangan,
            'user'       => $log->user?->name ?? 'Sistem',
            'waktu'      => $log->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
        ]);
        return response()->json(['success' => true, 'data' => $logs]);
    }

    /**
     * Hitung nomor rit berikutnya untuk driver di tanggal tertentu,
     * lintas jenis muatan (subsidi + non-subsidi) supaya nomor rit tidak bentrok.
     */
    public function nextRit(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|integer|exists:drivers,id',
            'tanggal'   => 'required|date',
        ]);

        $count = DaftarMuat::where('driver_id', $request->driver_id)
            ->whereDate('tanggal_muatan', $request->tanggal)
            ->when($request->filled('exclude_id'), fn($q) => $q->where('id', '!=', $request->exclude_id))
            ->count();

        return response()->json(['success' => true, 'next_rit' => $count + 1]);
    }

    public function statistik(Request $request): JsonResponse
    {
        $periode = $request->get('periode', 'bulan');

        $startDate = match ($periode) {
            'minggu'  => Carbon::now()->startOfWeek(Carbon::MONDAY),
            '6bulan'  => Carbon::now()->subMonths(6)->startOfDay(),
            'tahun'   => Carbon::now()->subYear()->startOfDay(),
            default   => Carbon::now()->startOfMonth(),
        };

        $wilayahMap = \App\Support\Wilayah::NAMA;

        // Statistik per driver
        $driverRaw = DB::table('daftar_muats as dm')
            ->join('drivers as d', 'd.id', '=', 'dm.driver_id')
            ->select('d.id as driver_id', 'd.nama_driver', 'dm.jenis_muatan', DB::raw('COUNT(*) as total'))
            ->where('dm.tanggal_muatan', '>=', $startDate->toDateString())
            ->whereNotNull('dm.driver_id')
            ->groupBy('d.id', 'd.nama_driver', 'dm.jenis_muatan')
            ->orderBy('d.nama_driver')
            ->get();

        $driverStats = $driverRaw->groupBy('driver_id')->map(function ($rows) {
            $first    = $rows->first();
            $subsidi  = $rows->where('jenis_muatan', 'subsidi')->first()?->total ?? 0;
            $nonSub   = $rows->where('jenis_muatan', 'non_subsidi')->first()?->total ?? 0;
            return [
                'driver_id'    => $first->driver_id,
                'nama_driver'  => $first->nama_driver,
                'subsidi'      => (int) $subsidi,
                'non_subsidi'  => (int) $nonSub,
                'total'        => (int) $subsidi + (int) $nonSub,
            ];
        })->values()->sortByDesc('total')->values();

        // Statistik per wilayah (kode_area)
        $wilayahRaw = DB::table('daftar_muat_items as dmi')
            ->join('daftar_muats as dm', 'dm.id', '=', 'dmi.daftar_muat_id')
            ->select('dmi.kode_area', DB::raw('COUNT(*) as total'))
            ->where('dm.tanggal_muatan', '>=', $startDate->toDateString())
            ->whereNotNull('dmi.kode_area')
            ->where('dmi.kode_area', '!=', '')
            ->groupBy('dmi.kode_area')
            ->orderByDesc('total')
            ->get();

        $wilayahStats = $wilayahRaw->map(fn($row) => [
            'kode_area'  => $row->kode_area,
            'nama'       => $wilayahMap[$row->kode_area] ?? $row->kode_area,
            'total'      => (int) $row->total,
        ])->values();

        // Summary
        $summary = DB::table('daftar_muats')
            ->select('jenis_muatan', DB::raw('COUNT(*) as total'))
            ->where('tanggal_muatan', '>=', $startDate->toDateString())
            ->groupBy('jenis_muatan')
            ->get()
            ->keyBy('jenis_muatan');

        return response()->json([
            'success' => true,
            'periode' => $periode,
            // Bahasa aplikasi bawaan 'en'; tanpa locale('id') bulan tampil "October".
            'mulai'   => $startDate->locale('id')->translatedFormat('d F Y'),
            'summary' => [
                'subsidi'     => (int) ($summary['subsidi']?->total ?? 0),
                'non_subsidi' => (int) ($summary['non_subsidi']?->total ?? 0),
                'total'       => (int) ($summary['subsidi']?->total ?? 0) + (int) ($summary['non_subsidi']?->total ?? 0),
            ],
            'driver_stats'  => $driverStats,
            'wilayah_stats' => $wilayahStats,
        ]);
    }

    public function suratJalanPdf(Request $request, int $id)
    {
        $muat = DaftarMuat::bolehDipegang($request->user())->with(['kendaraan', 'driver', 'items', 'checker'])->findOrFail($id);
        $pdf  = Pdf::loadView('pdf.surat-jalan', [
            'muat'       => $muat,
            'tanggal'    => Carbon::parse($muat->tanggal_muatan)->locale('id')->translatedFormat('d F Y'),
            'printed_at' => now()->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("SuratJalan_{$muat->id}_" . date('Ymd') . ".pdf");
    }

    /**
     * Rekap kiriman satu rentang tanggal, dikelompokkan per sopir.
     *
     * Bedanya dengan rekapHarianPdf: yang itu memotret satu hari, lengkap
     * dengan sopir yang tidak jalan dan yang izin — dipakai untuk mengecek
     * apakah armada hari ini sudah tertugaskan semua. Yang ini merekap
     * beberapa hari sekaligus dan hanya memuat sopir yang benar-benar
     * berangkat, mengikuti bentuk rekap tulis tangan yang sudah dipakai
     * kantor.
     */
    public function rekapPeriodePdf(Request $request)
    {
        $data = $request->validate([
            'dari'         => 'required|date',
            'sampai'       => 'required|date|after_or_equal:dari',
            'jenis_muatan' => 'nullable|in:subsidi,non_subsidi',
        ], [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal awal.',
        ]);

        $dari   = Carbon::parse($data['dari'])->startOfDay();
        $sampai = Carbon::parse($data['sampai'])->endOfDay();

        $muats = DaftarMuat::bolehDipegang($request->user())->with(['driver', 'items'])
            ->whereBetween('tanggal_muatan', [$dari->toDateString(), $sampai->toDateString()])
            ->when($request->filled('jenis_muatan'), fn ($q) => $q->where('jenis_muatan', $request->jenis_muatan))
            // Rit yang dibatalkan tidak pernah jalan, jadi tidak pantas masuk
            // rekap kiriman. Kalau ikut, tonase totalnya jadi lebih besar dari
            // yang benar-benar terkirim.
            ->where('status_perjalanan', '!=', 'Batal')
            ->whereNotNull('driver_id')
            ->has('items')
            ->orderBy('tanggal_muatan')
            ->orderBy('nomor_rit')
            ->get();

        $perSopir = $muats
            ->groupBy(fn ($m) => $m->driver?->nama_driver ?? 'Tanpa sopir')
            ->sortKeys()
            ->map(function ($ritSopir, $nama) {
                $rit = $ritSopir->map(fn ($m) => [
                    'tanggal' => Carbon::parse($m->tanggal_muatan)->format('d/m/Y'),
                    'tonase'  => number_format(round(((float) $m->total_muatan_ton) * 1000), 0, ',', '.'),
                    'kios'    => $m->items->pluck('lokasi_pengiriman')->filter()->values()->all(),
                ])->filter(fn ($r) => $r['kios'] !== [])->values();

                return [
                    'nama'  => $nama,
                    'rit'   => $rit,
                    // Dipakai rowspan kolom nama sopir: sebanyak seluruh baris
                    // kios miliknya, bukan jumlah ritnya.
                    'baris' => $rit->sum(fn ($r) => count($r['kios'])),
                ];
            })
            ->filter(fn ($s) => $s['baris'] > 0)
            ->values();

        $labelJenis = match ($request->jenis_muatan) {
            'subsidi'     => 'Subsidi',
            'non_subsidi' => 'Non Subsidi',
            default       => null,
        };

        $pdf = Pdf::loadView('pdf.rekap-periode', [
            'perSopir'    => $perSopir,
            'labelJenis'  => $labelJenis,
            'periode'     => $dari->locale('id')->translatedFormat('d F Y') . ' - ' . $sampai->locale('id')->translatedFormat('d F Y'),
            'dicetakOleh' => $request->user()->name,
            'dicetakPada' => now()->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            'RekapKiriman_' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.pdf'
        );
    }

    public function rekapHarianPdf(Request $request)
    {
        $request->validate([
            'tanggal'      => 'required|date',
            'jenis_muatan' => 'nullable|in:subsidi,non_subsidi',
        ]);
        $tanggal = Carbon::parse($request->tanggal)->toDateString();

        $muats = DaftarMuat::bolehDipegang($request->user())->with(['kendaraan', 'driver', 'items', 'checker'])
            ->whereDate('tanggal_muatan', $tanggal)
            ->when($request->filled('jenis_muatan'), fn($q) => $q->where('jenis_muatan', $request->jenis_muatan))
            ->has('items')
            ->orderBy('id')
            ->get();

        $driverIdsKirim = $muats->whereNotNull('driver_id')->pluck('driver_id')->unique();
        $driverIzin     = DriverIzin::with('driver')->whereDate('tanggal', $tanggal)->get();
        $driverIdsIzin  = $driverIzin->pluck('driver_id');
        $allDrivers     = drivers::where('status_kerja', 'Aktif')->orderBy('nama_driver')->get();
        $tidakJalan     = $allDrivers
            ->whereNotIn('id', $driverIdsKirim)
            ->whereNotIn('id', $driverIdsIzin)
            ->values();

        $pdf = Pdf::loadView('pdf.rekap-daftar-muat', [
            'muats'        => $muats,
            'tidakJalan'   => $tidakJalan,
            'driverIzin'   => $driverIzin,
            'tanggal'      => Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y'),
            'jenis_muatan' => $request->jenis_muatan ?? null,
            'printed_by'   => $request->user()->name,
            'printed_at'   => now()->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("RekapMuat_" . date('Ymd') . ".pdf");
    }
}
