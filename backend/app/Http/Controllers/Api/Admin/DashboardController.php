<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Izin;
use App\Models\KunjunganToko;
use App\Models\Presensi;
use App\Models\Sales;
use App\Support\Berkas;
use Illuminate\Support\Facades\URL;
use App\Traits\ApiResponse;
use App\Traits\UploadGambar;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    use ApiResponse, UploadGambar;

    /**
     * Statistik utama untuk dashboard admin (card summary)
     */
    public function statistics(): JsonResponse
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $bulanIni = Carbon::now('Asia/Jakarta')->format('Y-m');

        $stats = Cache::remember("dashboard_stats_{$today}", 300, function () use ($today, $bulanIni) {
            $totalSales    = Sales::where('is_active', true)->count();
            $hadir         = Presensi::whereDate('tanggal', $today)->count();
            $terlambat     = Presensi::whereDate('tanggal', $today)->where('status', 'terlambat')->count();
            $izinHariIni   = Izin::whereDate('tanggal', '<=', $today)
                                  ->whereDate('sampai_tanggal', '>=', $today)
                                  ->where('status', 'disetujui')
                                  ->count();
            $kunjunganHariIni = KunjunganToko::whereDate('created_at', $today)->count();
            $izinPending   = Izin::where('status', 'pending')->count();
            // ── Agregat bulan berjalan ──────────────────────────────────
            $awalBulan = Carbon::now('Asia/Jakarta')->startOfMonth();
            $kini      = Carbon::now('Asia/Jakarta');

            $presensiBulan = Presensi::whereBetween('tanggal', [$awalBulan->toDateString(), $today])
                ->selectRaw("COUNT(*) as total")
                ->selectRaw("SUM(CASE WHEN status='tepat_waktu' THEN 1 ELSE 0 END) as tepat_waktu")
                ->selectRaw("SUM(CASE WHEN status='terlambat'   THEN 1 ELSE 0 END) as terlambat")
                ->first();

            $absenBulanIni  = (int) ($presensiBulan->total       ?? 0);
            $tepatBulanIni  = (int) ($presensiBulan->tepat_waktu ?? 0);
            $lambatBulanIni = (int) ($presensiBulan->terlambat   ?? 0);

            $kunjunganBulanIni = KunjunganToko::whereBetween('created_at', [$awalBulan, $kini])->count();

            $izinDisetujuiBulanIni = Izin::whereBetween('tanggal', [$awalBulan->toDateString(), $today])
                ->where('status', 'disetujui')
                ->count();

            // Hari kerja yang sudah berjalan bulan ini (Senin–Sabtu, s/d hari ini).
            $hariKerja = $this->hitungHariKerja($awalBulan, $kini);

            return [
                'total_sales_aktif'    => $totalSales,
                'hadir_hari_ini'       => $hadir,
                'tidak_hadir_hari_ini' => max(0, $totalSales - $hadir - $izinHariIni),
                'terlambat_hari_ini'   => $terlambat,
                'izin_hari_ini'        => $izinHariIni,
                'kunjungan_hari_ini'   => $kunjunganHariIni,
                'izin_pending'         => $izinPending,

                // ── Bulan berjalan (dipakai widget "Ringkasan Bulan Ini") ──
                'total_absen_bulan_ini'     => $absenBulanIni,
                'tepat_waktu_bulan_ini'     => $tepatBulanIni,
                'terlambat_bulan_ini'       => $lambatBulanIni,
                'kunjungan_bulan_ini'       => $kunjunganBulanIni,
                'izin_disetujui_bulan_ini'  => $izinDisetujuiBulanIni,
                'hari_kerja_bulan_ini'      => $hariKerja,
                'target_hadir_bulan_ini'    => $hariKerja * $totalSales,
                'persen_tepat_waktu_bulan_ini' => $absenBulanIni > 0
                    ? round($tepatBulanIni / $absenBulanIni * 100)
                    : 0,
            ];
        });

        return $this->success($stats, 'Statistik dashboard berhasil diambil');
    }

    /**
     * Grafik kehadiran 30 hari terakhir untuk chart
     */
    public function attendanceChart(Request $request): JsonResponse
    {
        if ($request->group === 'bulan') {
            return $this->attendanceChartBulanan((int) ($request->months ?? 12));
        }

        $days  = (int) ($request->days ?? 30);
        $days  = min($days, 90);
        $start = Carbon::now('Asia/Jakarta')->subDays($days - 1)->toDateString();

        $data = Cache::remember("attendance_chart_v2_{$days}", 600, function () use ($start, $days) {
            // Kehadiran per hari — pakai DB::raw agar tanggal kembali sebagai string
            $presensis = DB::table('presensis')
                ->select(
                    DB::raw('DATE(tanggal) as tgl'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status='tepat_waktu' THEN 1 ELSE 0 END) as tepat_waktu"),
                    DB::raw("SUM(CASE WHEN status='terlambat' THEN 1 ELSE 0 END) as terlambat")
                )
                ->where('tanggal', '>=', $start)
                ->groupBy(DB::raw('DATE(tanggal)'))
                ->orderBy('tgl')
                ->get()
                ->keyBy('tgl');

            // Kunjungan per hari
            $kunjungans = DB::table('kunjungan_tokos')
                ->select(
                    DB::raw('DATE(created_at) as tgl'),
                    DB::raw('COUNT(*) as total_kunjungan')
                )
                ->where('created_at', '>=', $start)
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('tgl')
                ->get()
                ->keyBy('tgl');

            // Build full date range (key pakai 'tgl' string)
            $result = [];
            for ($i = 0; $i < $days; $i++) {
                $date = Carbon::now('Asia/Jakarta')->subDays($days - 1 - $i)->toDateString();
                $p    = $presensis->get($date);
                $k    = $kunjungans->get($date);
                $result[] = [
                    'tanggal'         => $date,
                    'total'           => (int) ($p?->total ?? 0),
                    'tepat_waktu'     => (int) ($p?->tepat_waktu ?? 0),
                    'terlambat'       => (int) ($p?->terlambat ?? 0),
                    'total_kunjungan' => (int) ($k?->total_kunjungan ?? 0),
                ];
            }

            return $result;
        });

        return $this->success($data, 'Grafik kehadiran berhasil diambil');
    }

    /**
     * Versi bulanan dari attendanceChart — dipakai saat ?group=bulan.
     *
     * Bentuk responsnya sengaja dibuat sama persis dengan versi harian
     * (key 'tanggal' berisi tanggal 1 tiap bulan), supaya komponen chart
     * di frontend tidak perlu tahu ini agregasi apa.
     */
    private function attendanceChartBulanan(int $months): JsonResponse
    {
        $months = max(1, min($months, 36));
        $awal   = Carbon::now('Asia/Jakarta')->startOfMonth()->subMonths($months - 1);

        $data = Cache::remember("attendance_chart_bulan_{$months}", 600, function () use ($awal, $months) {
            $presensis = DB::table('presensis')
                ->select(
                    DB::raw("DATE_FORMAT(tanggal, '%Y-%m') as bln"),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status='tepat_waktu' THEN 1 ELSE 0 END) as tepat_waktu"),
                    DB::raw("SUM(CASE WHEN status='terlambat' THEN 1 ELSE 0 END) as terlambat")
                )
                ->where('tanggal', '>=', $awal->toDateString())
                ->groupBy(DB::raw("DATE_FORMAT(tanggal, '%Y-%m')"))
                ->get()
                ->keyBy('bln');

            $kunjungans = DB::table('kunjungan_tokos')
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as bln"),
                    DB::raw('COUNT(*) as total_kunjungan')
                )
                ->where('created_at', '>=', $awal->toDateTimeString())
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->get()
                ->keyBy('bln');

            $hariIni = Carbon::now('Asia/Jakarta');

            $result = [];
            for ($i = 0; $i < $months; $i++) {
                $bulan = $awal->copy()->addMonths($i);
                $key   = $bulan->format('Y-m');
                $p     = $presensis->get($key);
                $k     = $kunjungans->get($key);

                $result[] = [
                    'tanggal'         => $bulan->toDateString(),
                    'total'           => (int) ($p?->total ?? 0),
                    'tepat_waktu'     => (int) ($p?->tepat_waktu ?? 0),
                    'terlambat'       => (int) ($p?->terlambat ?? 0),
                    'total_kunjungan' => (int) ($k?->total_kunjungan ?? 0),
                    // Pembanding untuk menghitung ketidakhadiran. Pada versi
                    // harian pembandingnya cukup jumlah sales; per bulan harus
                    // dikali jumlah hari kerja.
                    'hari_kerja'      => $this->hitungHariKerja($bulan, $hariIni),
                ];
            }

            return $this->pangkasBulanKosong(
                $result,
                fn ($b) => $b['total'] > 0 || $b['total_kunjungan'] > 0,
            );
        });

        return $this->success($data, 'Grafik kehadiran bulanan berhasil diambil');
    }

    /**
     * Buang bulan-bulan kosong di AWAL rentang — bulan sebelum sistem mulai
     * dipakai bikin chart menyisakan ruang kosong panjang. Yang dipangkas
     * hanya bagian depan; bulan kosong di tengah tetap ditampilkan karena
     * itu informasi nyata (memang tidak ada aktivitas).
     *
     * Kalau semua bulan kosong, kembalikan apa adanya supaya chart tidak
     * kosong melompong tanpa sumbu.
     *
     * @param  array<int, array<string, mixed>>  $bulanan
     * @param  callable(array<string, mixed>): bool  $adaIsinya
     * @return array<int, array<string, mixed>>
     */
    private function pangkasBulanKosong(array $bulanan, callable $adaIsinya): array
    {
        foreach ($bulanan as $i => $b) {
            if ($adaIsinya($b)) {
                return array_slice($bulanan, $i);
            }
        }

        return $bulanan;
    }

    /**
     * Jumlah hari kerja (Senin–Sabtu) dalam sebuah bulan. Untuk bulan yang
     * sedang berjalan dihitung sampai hari ini saja — kalau dihitung penuh,
     * sisa bulan yang belum terjadi ikut terhitung sebagai tidak hadir.
     */
    private function hitungHariKerja(Carbon $bulan, Carbon $hariIni): int
    {
        $mulai   = $bulan->copy()->startOfMonth();
        $selesai = $bulan->copy()->endOfMonth();

        if ($selesai->gt($hariIni)) {
            $selesai = $hariIni->copy();
        }

        if ($mulai->gt($selesai)) {
            return 0;
        }

        $jumlah = 0;
        for ($d = $mulai->copy(); $d->lte($selesai); $d->addDay()) {
            if (! $d->isSunday()) {
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /**
     * Grafik kunjungan per sales (untuk bar chart)
     */
    public function visitChart(Request $request): JsonResponse
    {
        $bulan = $request->bulan ?? Carbon::now('Asia/Jakarta')->format('Y-m');
        [$year, $month] = explode('-', $bulan);

        $data = Cache::remember("visit_chart_{$bulan}", 600, function () use ($year, $month) {
            return KunjunganToko::select('sales_id', DB::raw('COUNT(*) as total_kunjungan'))
                ->with(['sales:id,nama'])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->groupBy('sales_id')
                ->orderByDesc('total_kunjungan')
                ->limit(10)
                ->get()
                ->map(fn($k) => [
                    'sales_id'   => $k->sales_id,
                    'nama'       => $k->sales?->nama ?? 'Unknown',
                    'kunjungan'  => (int) $k->total_kunjungan,
                ])
                ->values()
                ->toArray();
        });

        return $this->success($data, 'Grafik kunjungan berhasil diambil');
    }

    /**
     * Aktivitas terbaru (realtime feed untuk dashboard)
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $limit = min((int) ($request->limit ?? 15), 50);

        $presensis = Presensi::with('sales:id,nama,foto_profil')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'type'       => $p->jam_pulang ? 'absen_pulang' : 'absen_masuk',
                'sales_nama' => $p->sales?->nama,
                'foto'       => $p->sales?->foto_profil_url,
                'waktu'      => $p->jam_pulang
                    ? Carbon::parse($p->tanggal->format('Y-m-d') . ' ' . $p->jam_pulang)->diffForHumans()
                    : Carbon::parse($p->tanggal->format('Y-m-d') . ' ' . $p->jam_masuk)->diffForHumans(),
                'status'     => $p->status_label,
                'created_at' => $p->updated_at,
            ]);

        $kunjungans = KunjunganToko::with('sales:id,nama,foto_profil')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($k) => [
                'type'       => 'kunjungan',
                'sales_nama' => $k->sales?->nama,
                'foto'       => $k->sales?->foto_profil_url,
                'waktu'      => $k->created_at->diffForHumans(),
                'status'     => 'Kunjungan: ' . ($k->nama_toko ?? '-'),
                'created_at' => $k->created_at,
            ]);

        $activities = $presensis->merge($kunjungans)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();

        return $this->success($activities, 'Aktivitas terbaru berhasil diambil');
    }

    /**
     * Summary bulanan per sales (untuk tabel ringkasan)
     */
    public function monthlySummary(Request $request): JsonResponse
    {
        // Support date range (dari/sampai) OR legacy bulan (Y-m) param
        if ($request->filled('dari') && $request->filled('sampai')) {
            $dari   = Carbon::parse($request->dari)->startOfDay();
            $sampai = Carbon::parse($request->sampai)->endOfDay();
            $cacheKey = "monthly_summary_{$dari->toDateString()}_{$sampai->toDateString()}";
        } else {
            $bulan = $request->bulan ?? Carbon::now('Asia/Jakarta')->format('Y-m');
            [$year, $month] = explode('-', $bulan);
            $dari   = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $sampai = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();
            $cacheKey = "monthly_summary_{$bulan}";
        }

        // Do not cache if the range includes today (data is live)
        $today     = Carbon::now('Asia/Jakarta')->toDateString();
        $cacheTtl  = $sampai->toDateString() >= $today ? 0 : 300;

        $build = function () use ($dari, $sampai) {
            $dateFrom = $dari->toDateString();
            $dateTo   = $sampai->toDateString();

            // Aggregate presensi per sales in one query
            $presensiAgg = DB::table('presensis')
                ->select('sales_id',
                    DB::raw("SUM(CASE WHEN status='tepat_waktu' THEN 1 ELSE 0 END) as tepat_waktu"),
                    DB::raw("SUM(CASE WHEN status='terlambat'   THEN 1 ELSE 0 END) as terlambat")
                )
                ->whereBetween('tanggal', [$dateFrom, $dateTo])
                ->groupBy('sales_id')
                ->get()
                ->keyBy('sales_id');

            // Aggregate kunjungan per sales in one query
            $kunjunganAgg = DB::table('kunjungan_tokos')
                ->select('sales_id', DB::raw('COUNT(*) as total'))
                ->whereBetween('created_at', [$dari, $sampai])
                ->groupBy('sales_id')
                ->get()
                ->keyBy('sales_id');

            // Aggregate izin per sales in one query
            $izinAgg = DB::table('izins')
                ->select('sales_id', DB::raw('COUNT(*) as total'))
                ->whereBetween('tanggal', [$dateFrom, $dateTo])
                ->where('status', 'disetujui')
                ->groupBy('sales_id')
                ->get()
                ->keyBy('sales_id');

            return Sales::where('is_active', true)
                ->orderBy('nama')
                ->get()
                ->map(function ($sales) use ($presensiAgg, $kunjunganAgg, $izinAgg) {
                    $p      = $presensiAgg->get($sales->id);
                    $tepat  = (int) ($p->tepat_waktu ?? 0);
                    $lambat = (int) ($p->terlambat  ?? 0);

                    return [
                        'id'              => $sales->id,
                        'sales_id'        => $sales->id,
                        'nama'            => $sales->nama,
                        'area'            => $sales->area,
                        'foto_profil_url' => $sales->foto_profil_url,
                        'total_hadir'     => $tepat + $lambat,
                        'tepat_waktu'     => $tepat,
                        'terlambat'       => $lambat,
                        'total_kunjungan' => (int) ($kunjunganAgg->get($sales->id)?->total ?? 0),
                        'total_izin'      => (int) ($izinAgg->get($sales->id)?->total       ?? 0),
                    ];
                })
                ->values();
        };

        $data = $cacheTtl > 0
            ? Cache::remember($cacheKey, $cacheTtl, $build)
            : $build();

        return $this->success($data, 'Summary berhasil diambil');
    }

    /**
     * List sales untuk admin (dengan filter, search, pagination)
     */
    public function salesList(Request $request): JsonResponse
    {
        $query = Sales::query()
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('nama', 'like', "%{$request->search}%")
                  ->orWhere('nik', 'like', "%{$request->search}%")
                  ->orWhere('area', 'like', "%{$request->search}%");
            }))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', (bool) $request->is_active))
            ->when($request->area, fn($q) => $q->where('area', $request->area))
            ->withCount([
                'presensi as total_hadir_bulan_ini' => function ($q) {
                    $q->whereMonth('tanggal', now()->month)->whereYear('tanggal', now()->year);
                },
            ])
            ->orderBy('nama');

        $perPage = min((int) ($request->per_page ?? 15), 100);

        return $this->success($query->paginate($perPage), 'Data sales berhasil diambil');
    }

    /**
     * List presensi untuk admin (dengan filter)
     */
    public function presensiList(Request $request): JsonResponse
    {
        $query = Presensi::with('sales:id,nama,area,foto_profil')
            ->when($request->search, fn($q) => $q->whereHas('sales', function ($sq) use ($request) {
                $sq->where('nama', 'like', '%' . $request->search . '%')
                   ->orWhere('area', 'like', '%' . $request->search . '%')
                   ->orWhere('nik',  'like', '%' . $request->search . '%');
            }))
            ->when($request->sales_id, fn($q) => $q->where('sales_id', $request->sales_id))
            ->when($request->tanggal_mulai, fn($q) => $q->whereDate('tanggal', '>=', $request->tanggal_mulai))
            ->when($request->tanggal_akhir, fn($q) => $q->whereDate('tanggal', '<=', $request->tanggal_akhir))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            // Tanpa filter "hari ini" tersembunyi: tanggal kosong berarti semua
            // presensi. Dulu kolom tanggal di layar tampak kosong tetapi yang
            // tampil hanya presensi hari ini.
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_masuk');

        $perPage = min((int) ($request->per_page ?? 20), 100);

        return $this->success($query->paginate($perPage), 'Data presensi berhasil diambil');
    }

    public function presensiDetail(int $id): JsonResponse
    {
        $p = Presensi::with('sales:id,nama,area,no_hp')->findOrFail($id);

        return $this->success([
            'id'                   => $p->id,
            'sales_id'             => $p->sales_id,
            'sales_nama'           => $p->sales?->nama,
            'sales_area'           => $p->sales?->area,
            'sales_no_hp'          => $p->sales?->no_hp,
            'tanggal'              => $p->tanggal,
            'status'               => $p->status,
            'jam_masuk'            => $p->jam_masuk,
            'jam_pulang'           => $p->jam_pulang,
            'location_masuk'       => $p->location_masuk,
            'location_pulang'      => $p->location_pulang,
            'alamat_masuk'         => $p->alamat_masuk,
            'alamat_pulang'        => $p->alamat_pulang,
            'keterangan'           => $p->keterangan,
            'foto_masuk_url'       => $p->foto_masuk  ? $this->tautanPresensi($p->id, 'masuk')  : null,
            'foto_pulang_url'      => $p->foto_pulang ? $this->tautanPresensi($p->id, 'pulang') : null,
            'created_at'           => $p->created_at?->toDateTimeString(),
        ]);
    }

    /**
     * Tautan foto presensi yang berlaku 12 jam.
     *
     * Bukan `Storage::url` lagi: fotonya sudah tidak disajikan langsung dari
     * `public/storage`, dan `<img src>` tidak pernah mengirim header
     * Authorization — jadi tanda tangan inilah yang membuat gambarnya tetap
     * tampil di dashboard tanpa membuka foto wajah karyawan untuk siapa saja.
     */
    private function tautanPresensi(int $id, string $jenis): string
    {
        return URL::temporarySignedRoute('berkas.presensi', now()->addHours(12), [
            'id' => $id, 'jenis' => $jenis,
        ]);
    }

    public function presensiUpdate(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('edit_presensi')) {
            return $this->error('Anda tidak memiliki izin untuk mengedit data presensi.', 403);
        }

        $p = Presensi::findOrFail($id);

        $validated = $request->validate([
            'tanggal'    => 'required|date_format:Y-m-d',
            'jam_masuk'  => 'nullable|date_format:H:i',
            // BUG FIX #1: hapus after:jam_masuk — rule ini gagal saat jam_masuk null
            // karena Laravel tidak bisa membandingkan terhadap null sebagai date.
            // Validasi urutan jam sudah ditangani di frontend (guard jamPulang <= jamMasuk).
            'jam_pulang' => 'nullable|date_format:H:i',
            'status'     => 'required|in:tepat_waktu,terlambat,terlambat_izin',
            'keterangan' => 'nullable|string|max:500',
        ]);

        // BUG FIX #2: Jangan auto-recalculate status dari jam.
        // Status adalah pilihan eksplisit admin — termasuk terlambat_izin yang
        // tidak bisa dihasilkan dari kalkulasi waktu. Frontend sudah menampilkan
        // hint dan Select dropdown yang menjadi single source of truth untuk status.
        // Logika lama: `$validated['status'] === $p->status` tidak bisa membedakan
        // "admin tidak ubah status" vs "admin sengaja pilih status yang sama",
        // sehingga terlambat_izin selalu di-overwrite saat jam_masuk diubah.

        $p->update($validated);

        \Log::info('Admin update presensi', [
            'presensi_id' => $id,
            'admin_id'    => auth()->id(),
            'changes'     => $validated,
        ]);

        return $this->success([
            'id'         => $p->id,
            'tanggal'    => $p->tanggal,
            'jam_masuk'  => $p->jam_masuk,
            'jam_pulang' => $p->jam_pulang,
            'status'     => $p->status,
            'keterangan' => $p->keterangan,
        ], 'Data presensi berhasil diperbarui');
    }

    public function presensiDelete(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->hasPermission('delete_presensi')) {
            return $this->error('Anda tidak memiliki izin untuk menghapus data presensi.', 403);
        }

        $p = Presensi::findOrFail($id);

        // BUG FIX #3 (log order): log ditulis SETELAH delete berhasil agar
        // audit trail tidak mencatat penghapusan yang gagal.
        $p->delete();

        \Log::info('Admin delete presensi', [
            'presensi_id' => $id,
            'sales_id'    => $p->sales_id,
            'tanggal'     => $p->tanggal,
            'admin_id'    => auth()->id(),
        ]);

        return $this->success(null, 'Data presensi berhasil dihapus');
    }

    public function kunjunganList(Request $request): JsonResponse
    {
        $query = KunjunganToko::with(['sales:id,nama,area', 'penanggap:id,name'])
            ->saringTanggapan($request->tanggapan)
            ->when($request->search, fn($q) => $q->where(function ($sq) use ($request) {
                $sq->where('nama_toko', 'like', '%' . $request->search . '%')
                   ->orWhereHas('sales', fn($s) => $s->where('nama', 'like', '%' . $request->search . '%')
                       ->orWhere('area', 'like', '%' . $request->search . '%'));
            }))
            ->when($request->sales_id,      fn($q) => $q->where('sales_id', $request->sales_id))
            ->when($request->tanggal_mulai, fn($q) => $q->whereDate('created_at', '>=', $request->tanggal_mulai))
            ->when($request->tanggal_akhir, fn($q) => $q->whereDate('created_at', '<=', $request->tanggal_akhir))
            ->when($request->is_suspicious !== null && $request->is_suspicious !== '',
                fn($q) => $q->where('is_suspicious', filter_var($request->is_suspicious, FILTER_VALIDATE_BOOLEAN)))
            // Tidak ada lagi filter "hari ini" tersembunyi bila tanggal kosong.
            // Dulu tanggal kosong berarti hari ini, padahal kolom tanggal di
            // layar tampak kosong, sehingga pilihan 50 baris hanya menampilkan
            // kunjungan hari ini. Kini tanggal kosong berarti semua kunjungan.
            ->orderByDesc('created_at');

        $perPage = min((int) ($request->per_page ?? 20), 100);

        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function ($k) {
            $createdJkt = $k->created_at?->timezone('Asia/Jakarta');
            return [
                'id'            => $k->id,
                'sales_id'      => $k->sales_id,
                'sales_nama'    => $k->sales?->nama,
                'sales_area'    => $k->sales?->area,
                'nama_toko'     => $k->nama_toko,
                'keterangan'    => $k->keterangan,
                'is_suspicious' => $k->is_suspicious,
                'foto_url'      => $k->foto_kunjungan_url,
                'location'      => $k->location,
                'alamat'        => $k->alamat,
                'tanggal'       => $createdJkt?->format('Y-m-d'),
                'jam'           => $createdJkt?->format('H:i'),
                'created_at'    => $createdJkt?->toDateTimeString(),
                'tanggapan'     => $k->tanggapanArray(),
            ];
        });

        return $this->success($paginated, 'Data kunjungan berhasil diambil');
    }

    /** Sales yang punya kunjungan, untuk saringan di daftar kunjungan. */
    public function kunjunganSalesOptions(): JsonResponse
    {
        $list = Sales::whereIn('id', KunjunganToko::select('sales_id')->distinct())
            ->orderBy('nama')
            ->get(['id', 'nama', 'area'])
            ->map(fn($s) => ['id' => $s->id, 'label' => $s->area ? "{$s->nama} ({$s->area})" : $s->nama]);

        return $this->success($list);
    }

    public function kunjunganDetail(int $id): JsonResponse
    {
        $k = KunjunganToko::with(['sales:id,nama,area,no_hp', 'penanggap:id,name'])->findOrFail($id);
        $createdJkt = $k->created_at?->timezone('Asia/Jakarta');

        return $this->success([
            'id'               => $k->id,
            'sales_id'         => $k->sales_id,
            'sales_nama'       => $k->sales?->nama,
            'sales_area'       => $k->sales?->area,
            'sales_no_hp'      => $k->sales?->no_hp,
            'nama_toko'        => $k->nama_toko,
            'keterangan'       => $k->keterangan,
            'location'         => $k->location,
            'alamat'           => $k->alamat,
            'is_suspicious'    => $k->is_suspicious,
            'suspicious_reason'=> $k->suspicious_reason,
            'google_maps_url'  => $k->location
                ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($k->location)
                : null,
            'checkout_at'      => $k->checkout_at?->timezone('Asia/Jakarta')->toDateTimeString(),
            'durasi_label'     => $k->checkout_at
                ? $this->formatDurasi($k->created_at->diffInSeconds($k->checkout_at))
                : null,
            'foto_url'         => $k->foto_kunjungan_url,
            'tanggal'          => $createdJkt?->locale('id')->translatedFormat('d F Y'),
            'jam'              => $createdJkt?->format('H:i'),
            'created_at'       => $createdJkt?->toDateTimeString(),
            'tanggapan'        => $k->tanggapanArray(),
        ]);
    }

    public function kunjunganDelete(int $id): JsonResponse
    {
        if (!$this->isDeveloper($id)) {
            // Cek permission delete_kunjungan untuk non-developer
            if (!request()->user()->hasPermission('delete_kunjungan')) {
                return $this->error('Anda tidak memiliki izin untuk menghapus data kunjungan.', 403);
            }
        }
        KunjunganToko::findOrFail($id)->delete();
        return $this->success(null, 'Data kunjungan berhasil dihapus');
    }

    public function kunjunganUpdate(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isFullAccess()) {
            return $this->error('Hanya developer/owner yang dapat mengedit kunjungan.', 403);
        }

        $k = KunjunganToko::findOrFail($id);

        $validated = $request->validate([
            'nama_toko'      => 'required|string|max:255',
            'keterangan'     => 'nullable|string|max:500',
            'created_at'     => 'nullable|date_format:Y-m-d H:i:s',
            'checkout_at'    => 'nullable|date_format:Y-m-d H:i:s',
            'foto_kunjungan' => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('foto_kunjungan')) {
            if (!str_starts_with((string) $k->foto_kunjungan, 'http')) {
                Berkas::hapus($k->foto_kunjungan);
            }
            $validated['foto_kunjungan'] = $this->uploadCompressed(
                $request->file('foto_kunjungan'), 'kunjungan'
            );
        } else {
            unset($validated['foto_kunjungan']);
        }

        // Input admin sudah berupa waktu WIB dan APP_TIMEZONE aplikasi adalah Asia/Jakarta.
        // Jangan konversi ke UTC di sini, karena akan membuat jam mundur 7 jam saat ditampilkan.
        if (!empty($validated['created_at'])) {
            $validated['created_at'] = Carbon::createFromFormat('Y-m-d H:i:s', $validated['created_at'], 'Asia/Jakarta');
        }
        if (!empty($validated['checkout_at'])) {
            $validated['checkout_at'] = Carbon::createFromFormat('Y-m-d H:i:s', $validated['checkout_at'], 'Asia/Jakarta');
        }

        $k->update($validated);

        \Log::info('Admin update kunjungan', [
            'kunjungan_id' => $id,
            'admin_id'     => $request->user()->id,
            'changes'      => array_keys($validated),
        ]);

        return $this->success([
            'id'        => $k->id,
            'nama_toko' => $k->nama_toko,
            'keterangan'=> $k->keterangan,
            'foto_url'  => $k->foto_kunjungan_url,
        ], 'Data kunjungan berhasil diperbarui');
    }

    public function kunjunganStore(Request $request): JsonResponse
    {
        if (!$request->user()->isFullAccess()) {
            return $this->error('Hanya developer/owner yang dapat menambah kunjungan manual.', 403);
        }

        $validated = $request->validate([
            'sales_id'       => 'required|integer|exists:sales,id',
            'nama_toko'      => 'required|string|max:255',
            'keterangan'     => 'nullable|string|max:500',
            'location'       => 'nullable|string|max:255',
            'created_at'     => 'required|date_format:Y-m-d H:i:s',
            'checkout_at'    => 'nullable|date_format:Y-m-d H:i:s',
            'foto_kunjungan' => 'nullable|image|max:5120',
        ]);

        try {
            $fotoPath = null;
            if ($request->hasFile('foto_kunjungan')) {
                $fotoPath = $this->uploadCompressed($request->file('foto_kunjungan'), 'kunjungan');
            }

            $k = KunjunganToko::create([
                'sales_id'       => $validated['sales_id'],
                'nama_toko'      => $validated['nama_toko'],
                'keterangan'     => $validated['keterangan'] ?? null,
                'location'       => $validated['location'] ?? '0,0',
                'lat'            => null,
                'lng'            => null,
                'foto_kunjungan' => $fotoPath ?? '',
                'is_suspicious'  => false,
                'created_at'     => Carbon::createFromFormat('Y-m-d H:i:s', $validated['created_at'], 'Asia/Jakarta'),
                'checkout_at'    => isset($validated['checkout_at'])
                    ? Carbon::createFromFormat('Y-m-d H:i:s', $validated['checkout_at'], 'Asia/Jakarta')
                    : null,
            ]);

            \Log::info('Developer tambah kunjungan manual', [
                'kunjungan_id' => $k->id,
                'admin_id'     => $request->user()->id,
                'sales_id'     => $k->sales_id,
            ]);

            return $this->success(['id' => $k->id], 'Kunjungan berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            \Log::error('kunjunganStore error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->error('Gagal menyimpan: ' . $e->getMessage(), 500);
        }
    }

    private function isDeveloper(int $id): bool
    {
        return request()->user()->isFullAccess();
    }

    private function formatDurasi(int $detik): string
    {
        if ($detik >= 3600) {
            return sprintf('%d jam %d menit', intdiv($detik, 3600), intdiv($detik % 3600, 60));
        }
        return sprintf('%d menit', intdiv($detik, 60));
    }

    /**
     * List izin untuk admin (dengan filter, search & approval)
     */
    public function izinList(Request $request): JsonResponse
    {
        $query = Izin::with('sales:id,nama,area')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            // Status kosong berarti semua status. Dulu otomatis "pending", jadi
            // pilihan "Semua Status" di halaman Izin tidak pernah bekerja dan
            // rincian di Laporan Izin hanya berisi izin pending.
            ->when($request->sales_id, fn($q) => $q->where('sales_id', $request->sales_id))
            // Periode, dipakai rincian per sales di Laporan Izin. Kolomnya sama
            // dengan izinRecap supaya rincian cocok dengan angka rekapnya.
            ->when($request->dari,   fn($q) => $q->whereDate('tanggal', '>=', $request->dari))
            ->when($request->sampai, fn($q) => $q->whereDate('tanggal', '<=', $request->sampai))
            ->when($request->jenis_izin, fn($q) => $q->where('jenis_izin', $request->jenis_izin))
            ->when($request->search, fn($q) => $q->whereHas('sales', function ($sq) use ($request) {
                $sq->where('nama', 'like', '%' . $request->search . '%')
                   ->orWhere('area', 'like', '%' . $request->search . '%');
            }))
            ->orderByDesc('created_at');

        $perPage = min((int) ($request->per_page ?? 20), 100);

        return $this->success($query->paginate($perPage), 'Data izin berhasil diambil');
    }

    /**
     * Rekap izin per sales dalam periode (untuk halaman laporan)
     */
    public function izinRecap(Request $request): JsonResponse
    {
        $request->validate([
            'dari'   => 'required|date',
            'sampai' => 'required|date|after_or_equal:dari',
        ]);

        $dari   = Carbon::parse($request->dari)->startOfDay();
        $sampai = Carbon::parse($request->sampai)->endOfDay();

        $dateFrom   = $dari->toDateString();
        $dateTo     = $sampai->toDateString();
        $jenisFilter = $request->jenis_izin;

        // Ambil semua sales aktif
        $salesList = Sales::where('is_active', true)
            ->select('id', 'nama', 'area')
            ->orderBy('nama')
            ->get()
            ->keyBy('id');

        // Satu query: count per sales per status
        $statusAgg = DB::table('izins')
            ->select('sales_id', 'status', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('tanggal', [$dateFrom, $dateTo])
            ->whereIn('sales_id', $salesList->keys())
            ->when($jenisFilter, fn($q) => $q->where('jenis_izin', $jenisFilter))
            ->groupBy('sales_id', 'status')
            ->get();

        // Satu query: total hari izin disetujui per sales
        $hariAgg = DB::table('izins')
            ->select('sales_id', DB::raw('SUM(DATEDIFF(sampai_tanggal, tanggal) + 1) as total_hari'))
            ->whereBetween('tanggal', [$dateFrom, $dateTo])
            ->whereIn('sales_id', $salesList->keys())
            ->where('status', 'disetujui')
            ->when($jenisFilter, fn($q) => $q->where('jenis_izin', $jenisFilter))
            ->groupBy('sales_id')
            ->get()
            ->keyBy('sales_id');

        // Satu query: jenis izin terbanyak (disetujui) per sales
        $jenisAgg = DB::table('izins')
            ->select('sales_id', 'jenis_izin', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('tanggal', [$dateFrom, $dateTo])
            ->whereIn('sales_id', $salesList->keys())
            ->where('status', 'disetujui')
            ->when($jenisFilter, fn($q) => $q->where('jenis_izin', $jenisFilter))
            ->groupBy('sales_id', 'jenis_izin')
            ->get()
            ->groupBy('sales_id')
            ->map(fn($rows) => $rows->sortByDesc('cnt')->first()?->jenis_izin);

        // Build lookup: sales_id → [status → count]
        $statusMap = [];
        foreach ($statusAgg as $row) {
            $statusMap[$row->sales_id][$row->status] = (int) $row->cnt;
        }

        $perSales = $salesList
            ->filter(fn($s) => isset($statusMap[$s->id]))
            ->map(function ($sales) use ($statusMap, $hariAgg, $jenisAgg) {
                $counts    = $statusMap[$sales->id] ?? [];
                $disetujui = $counts['disetujui'] ?? 0;
                $ditolak   = $counts['ditolak']   ?? 0;
                $pending   = $counts['pending']   ?? 0;

                return [
                    'sales_id'        => $sales->id,
                    'nama'            => $sales->nama,
                    'area'            => $sales->area,
                    'disetujui'       => $disetujui,
                    'ditolak'         => $ditolak,
                    'pending'         => $pending,
                    'total'           => $disetujui + $ditolak + $pending,
                    'total_hari_izin' => (int) ($hariAgg->get($sales->id)?->total_hari ?? 0),
                    'jenis_terbanyak' => $jenisAgg->get($sales->id),
                ];
            })
            ->sortBy('nama')
            ->values();

        $summary = [
            'total'     => $perSales->sum('total'),
            'disetujui' => $perSales->sum('disetujui'),
            'ditolak'   => $perSales->sum('ditolak'),
            'pending'   => $perSales->sum('pending'),
        ];

        return $this->success([
            'summary'   => $summary,
            'per_sales' => $perSales,
        ], 'Rekap izin berhasil diambil');
    }

    /**
     * Approve / reject izin + kirim FCM notifikasi ke sales
     */
    public function approveIzin(Request $request, int $id): JsonResponse
    {
        $request->validate([
            // Vue mengirim 'approved'/'rejected' — petakan ke nilai ENUM DB
            'action'       => 'required|in:approved,rejected',
            'alasan_tolak' => 'required_if:action,rejected|nullable|string|max:500',
        ]);

        $izin = Izin::findOrFail($id);

        if ($izin->status !== 'pending') {
            return $this->error('Izin ini sudah diproses sebelumnya.', 400);
        }

        // Petakan nilai dari Vue ('approved'/'rejected') ke ENUM DB ('disetujui'/'ditolak')
        $statusDb = $request->action === 'approved' ? 'disetujui' : 'ditolak';

        $izin->update([
            'status'       => $statusDb,
            'alasan_tolak' => $request->action === 'rejected' ? $request->alasan_tolak : null,
        ]);

        // Kirim FCM notifikasi ke sales via job (background, non-blocking)
        \App\Jobs\ProcessIzin::dispatch($izin->id);

        $message = $request->action === 'approved'
            ? 'Izin berhasil disetujui'
            : 'Izin berhasil ditolak';

        return $this->success($izin->fresh()->load('sales:id,nama'), $message);
    }

    /**
     * Hapus data izin (admin) — semua status boleh dihapus oleh admin
     */
    public function deleteIzin(int $id): JsonResponse
    {
        $izin = \App\Models\Izin::findOrFail($id);
        $izin->delete();
        return $this->success(null, 'Data izin berhasil dihapus');
    }

    /**
     * Detail sales beserta statistik
     */
    public function salesDetail(int $salesId): JsonResponse
    {
        $sales = Sales::findOrFail($salesId);

        $today   = Carbon::now('Asia/Jakarta')->toDateString();
        $month   = Carbon::now('Asia/Jakarta')->month;
        $year    = Carbon::now('Asia/Jakarta')->year;

        $presensiHariIni = Presensi::where('sales_id', $salesId)
            ->whereDate('tanggal', $today)
            ->first();

        $statsbulan = [
            'total_hadir'    => Presensi::where('sales_id', $salesId)->whereMonth('tanggal', $month)->whereYear('tanggal', $year)->count(),
            'terlambat'      => Presensi::where('sales_id', $salesId)->where('status', 'terlambat')->whereMonth('tanggal', $month)->whereYear('tanggal', $year)->count(),
            'total_kunjungan'=> KunjunganToko::where('sales_id', $salesId)->whereMonth('created_at', $month)->whereYear('created_at', $year)->count(),
            'total_izin'     => Izin::where('sales_id', $salesId)->where('status', 'disetujui')->whereMonth('tanggal', $month)->whereYear('tanggal', $year)->count(),
        ];

        return $this->success([
            'sales'           => $sales,
            'presensi_hari_ini' => $presensiHariIni,
            'statistik_bulan' => $statsbulan,
        ], 'Detail sales berhasil diambil');
    }

    // ── NOTIFIKASI ADMIN ─────────────────────────────────────────────────────

    public function notificationList(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);
        return $this->success($notifications);
    }

    public function notificationUnreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        return $this->success(['count' => $count]);
    }

    public function notificationMarkRead(Request $request, $id): JsonResponse
    {
        $notif = $request->user()->notifications()->find($id);
        $notif?->markAsRead();
        return $this->success(null, 'Notifikasi ditandai dibaca');
    }

    public function notificationMarkAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        return $this->success(null, 'Semua notifikasi ditandai dibaca');
    }

    // ── MAPS / PETA SEBARAN ───────────────────────────────────────────────────

    public function mapsKunjungan(Request $request): JsonResponse
    {
        $tanggal = $request->get('tanggal', Carbon::today('Asia/Jakarta')->toDateString());

        $kunjungan = KunjunganToko::with('sales:id,nama,area,foto_profil')
            ->whereDate('created_at', $tanggal)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderBy('sales_id')
            ->orderBy('created_at')
            ->get(['id', 'sales_id', 'nama_toko', 'lat', 'lng', 'created_at', 'checkout_at', 'keterangan']);

        // Compute duration and format timestamps in WIB
        $kunjungan = $kunjungan->map(function ($k) {
            $durasiDetik = null;
            $durasiLabel = null;
            if ($k->checkout_at) {
                $durasiDetik = $k->created_at->diffInSeconds($k->checkout_at);
                $durasiLabel = $this->formatDurasi($durasiDetik);
            }
            $createdJkt  = $k->created_at->timezone('Asia/Jakarta');
            $checkoutJkt = $k->checkout_at?->timezone('Asia/Jakarta');
            return [
                'id'          => $k->id,
                'sales_id'    => $k->sales_id,
                'sales'       => [
                    'nama' => $k->sales?->nama,
                    'area' => $k->sales?->area,
                    'foto' => $k->sales?->foto_profil_url,
                ],
                'nama_toko'    => $k->nama_toko,
                'keterangan'   => $k->keterangan,
                'lat'          => $k->lat,
                'lng'          => $k->lng,
                'created_at'   => $createdJkt->toIso8601String(),
                'checkout_at'  => $checkoutJkt?->toIso8601String(),
                'durasi_detik' => $durasiDetik,
                'durasi_label' => $durasiLabel,
                'status'       => $k->checkout_at ? 'selesai' : 'dalam_toko',
            ];
        });

        // Presensi locations for the same date (with WIB timestamps)
        $presensis = Presensi::with('sales:id,nama,area')
            ->whereDate('tanggal', $tanggal)
            ->get(['id', 'sales_id', 'tanggal', 'jam_masuk', 'jam_pulang', 'status', 'location_masuk', 'location_pulang'])
            ->map(function ($p) {
                $parseLoc = function ($loc) {
                    if (!$loc) return null;
                    $parts = array_map('floatval', explode(',', $loc, 2));
                    // Reject invalid 0,0 coordinates
                    if (count($parts) < 2 || ($parts[0] === 0.0 && $parts[1] === 0.0)) return null;
                    return $parts;
                };
                $masuk  = $parseLoc($p->location_masuk);
                $pulang = $parseLoc($p->location_pulang);
                return [
                    'id'         => $p->id,
                    'sales_id'   => $p->sales_id,
                    'sales'      => ['nama' => $p->sales?->nama, 'area' => $p->sales?->area],
                    'jam_masuk'  => $p->jam_masuk,
                    'jam_pulang' => $p->jam_pulang,
                    'status'     => $p->status,
                    'lat_masuk'  => $masuk  ? $masuk[0]  : null,
                    'lng_masuk'  => $masuk  ? $masuk[1]  : null,
                    'lat_pulang' => $pulang ? $pulang[0] : null,
                    'lng_pulang' => $pulang ? $pulang[1] : null,
                ];
            })->filter(fn($p) => $p['lat_masuk'] || $p['lat_pulang'])->values();

        // Build presensi lookup by sales_id for polyline start/end points
        $presensiMap = $presensis->keyBy('sales_id');

        // Group kunjungan by sales; polyline = absen masuk → kunjungan (urut waktu) → absen pulang
        $perSales = $kunjungan->groupBy('sales_id')->map(function ($items, $salesId) use ($presensiMap) {
            $first = $items->first();
            $p     = $presensiMap->get($salesId);

            // Urutkan kunjungan berdasarkan created_at agar jalur runtut
            $sorted = $items->sortBy('created_at')->values();

            // Kumpulkan semua titik dengan timestamp untuk sorting akhir
            $points = collect();

            // Titik absen masuk (paling awal)
            if ($p && $p['lat_masuk'] && $p['lng_masuk']) {
                $points->push([
                    'ts'  => $p['jam_masuk'] ?? '00:00:00',
                    'lat' => $p['lat_masuk'],
                    'lng' => $p['lng_masuk'],
                    'type' => 'masuk',
                ]);
            }

            // Setiap kunjungan: titik tiba (created_at) & titik keluar (checkout_at)
            foreach ($sorted as $k) {
                $points->push([
                    'ts'  => $k['created_at'],
                    'lat' => $k['lat'],
                    'lng' => $k['lng'],
                    'type' => 'kunjungan_tiba',
                ]);
                // Jika sudah checkout, tambah titik keluar (posisi sama, waktu checkout)
                if (!empty($k['checkout_at'])) {
                    $points->push([
                        'ts'  => $k['checkout_at'],
                        'lat' => $k['lat'],
                        'lng' => $k['lng'],
                        'type' => 'kunjungan_keluar',
                    ]);
                }
            }

            // Titik absen pulang (paling akhir)
            if ($p && $p['lat_pulang'] && $p['lng_pulang']) {
                $points->push([
                    'ts'  => $p['jam_pulang'] ?? '23:59:59',
                    'lat' => $p['lat_pulang'],
                    'lng' => $p['lng_pulang'],
                    'type' => 'pulang',
                ]);
            }

            // Sort semua titik berdasarkan timestamp
            $sortedPoints = $points->sortBy('ts')->values();

            // Hapus titik duplikat berurutan (lat/lng sama)
            $polyline = collect();
            $prevLat = null; $prevLng = null;
            foreach ($sortedPoints as $pt) {
                if ($pt['lat'] === $prevLat && $pt['lng'] === $prevLng) continue;
                $polyline->push([$pt['lat'], $pt['lng']]);
                $prevLat = $pt['lat']; $prevLng = $pt['lng'];
            }

            return [
                'sales_id'   => $salesId,
                'sales_nama' => $first['sales']['nama'] ?? '-',
                'sales_area' => $first['sales']['area'] ?? '-',
                'kunjungan'  => $sorted,
                'polyline'   => $polyline->values(),
            ];
        })->values();

        $salesAktif = Sales::where('is_active', true)->count();

        return $this->success([
            'kunjungan'   => $kunjungan->values(),
            'presensi'    => $presensis,
            'per_sales'   => $perSales,
            'total'       => $kunjungan->count(),
            'tanggal'     => $tanggal,
            'sales_aktif' => $salesAktif,
        ]);
    }

    /**
     * Trend muatan harian 30 hari terakhir (untuk line chart dashboard)
     */
    public function muatanTrend(Request $request): JsonResponse
    {
        if ($request->group === 'bulan') {
            return $this->muatanTrendBulanan((int) ($request->months ?? 12));
        }

        $days  = min((int) ($request->days ?? 30), 90);
        $start = Carbon::now('Asia/Jakarta')->subDays($days - 1)->toDateString();

        $data = Cache::remember("muatan_trend_{$days}", 300, function () use ($start, $days) {
            $rows = DB::table('daftar_muats')
                ->select(
                    DB::raw('DATE(daftar_muats.tanggal_muatan) as tgl'),
                    DB::raw('SUM(daftar_muats.total_muatan_ton) as total_ton'),
                    DB::raw('COUNT(daftar_muats.id) as total_jadwal')
                )
                ->where('daftar_muats.tanggal_muatan', '>=', $start)
                ->groupBy(DB::raw('DATE(daftar_muats.tanggal_muatan)'))
                ->get()
                ->keyBy('tgl');

            $result = [];
            for ($i = 0; $i < $days; $i++) {
                $date = Carbon::now('Asia/Jakarta')->subDays($days - 1 - $i)->toDateString();
                $r    = $rows->get($date);
                $result[] = [
                    'tanggal'      => $date,
                    'total_ton'    => $r ? (float) $r->total_ton    : 0,
                    'total_jadwal' => $r ? (int)   $r->total_jadwal : 0,
                ];
            }

            return $result;
        });

        return $this->success($data, 'Trend muatan berhasil diambil');
    }

    /**
     * Versi bulanan dari muatanTrend — dipakai saat ?group=bulan.
     * Bentuk responsnya sama dengan versi harian, 'tanggal' = tanggal 1.
     */
    private function muatanTrendBulanan(int $months): JsonResponse
    {
        $months = max(1, min($months, 36));
        $awal   = Carbon::now('Asia/Jakarta')->startOfMonth()->subMonths($months - 1);

        $data = Cache::remember("muatan_trend_bulan_{$months}", 300, function () use ($awal, $months) {
            $rows = DB::table('daftar_muats')
                ->select(
                    DB::raw("DATE_FORMAT(tanggal_muatan, '%Y-%m') as bln"),
                    DB::raw('SUM(total_muatan_ton) as total_ton'),
                    DB::raw('COUNT(id) as total_jadwal')
                )
                ->where('tanggal_muatan', '>=', $awal->toDateString())
                ->groupBy(DB::raw("DATE_FORMAT(tanggal_muatan, '%Y-%m')"))
                ->get()
                ->keyBy('bln');

            $result = [];
            for ($i = 0; $i < $months; $i++) {
                $bulan = $awal->copy()->addMonths($i);
                $r     = $rows->get($bulan->format('Y-m'));

                $result[] = [
                    'tanggal'      => $bulan->toDateString(),
                    'total_ton'    => $r ? (float) $r->total_ton    : 0,
                    'total_jadwal' => $r ? (int)   $r->total_jadwal : 0,
                ];
            }

            return $this->pangkasBulanKosong(
                $result,
                fn ($b) => $b['total_ton'] > 0 || $b['total_jadwal'] > 0,
            );
        });

        return $this->success($data, 'Trend muatan bulanan berhasil diambil');
    }

    /**
     * Statistik pengiriman — driver, kendaraan, muatan (untuk dashboard)
     */
    public function pengirimanStats(): JsonResponse
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $bulan = Carbon::now('Asia/Jakarta');

        $data = Cache::remember("pengiriman_stats_{$today}", 120, function () use ($today, $bulan) {
            // ── Driver ──────────────────────────────────────────────────────
            $totalDriver  = DB::table('drivers')->count();
            $driverAktif  = DB::table('drivers')->where('status_kerja', 'Aktif')->count();

            // ── Kendaraan ───────────────────────────────────────────────────
            $totalKendaraan    = DB::table('kendaraans')->count();
            $kendaraanTersedia = DB::table('kendaraans')->where('status_kendaraan', 'Tersedia')->count();
            $kendaraanJalan    = DB::table('kendaraans')->where('status_kendaraan', 'Tidak Tersedia')->count();

            // ── Muatan hari ini ─────────────────────────────────────────────
            $muatanHariIni = DB::table('daftar_muats')
                ->whereDate('daftar_muats.tanggal_muatan', $today)
                ->sum('daftar_muats.total_muatan_ton');

            $jadwalHariIni    = DB::table('daftar_muats')->whereDate('tanggal_muatan', $today)->count();
            $sudahDriverHariIni = DB::table('daftar_muats')
                ->whereDate('tanggal_muatan', $today)
                ->whereNotNull('driver_id')
                ->count();
            $belumDriverHariIni = $jadwalHariIni - $sudahDriverHariIni;

            // ── Muatan bulan ini ────────────────────────────────────────────
            $muatanBulanIni = DB::table('daftar_muats')
                ->whereYear('daftar_muats.tanggal_muatan', $bulan->year)
                ->whereMonth('daftar_muats.tanggal_muatan', $bulan->month)
                ->sum('daftar_muats.total_muatan_ton');

            $jadwalBulanIni = DB::table('daftar_muats')
                ->whereYear('tanggal_muatan', $bulan->year)
                ->whereMonth('tanggal_muatan', $bulan->month)
                ->count();

            return [
                'driver' => [
                    'total'  => $totalDriver,
                    'aktif'  => $driverAktif,
                    'nonaktif' => $totalDriver - $driverAktif,
                ],
                'kendaraan' => [
                    'total'         => $totalKendaraan,
                    'tersedia'      => $kendaraanTersedia,
                    'tidak_tersedia'=> $kendaraanJalan,
                ],
                'muatan_hari_ini' => [
                    'total_ton'    => (float) $muatanHariIni,
                    'jadwal'       => $jadwalHariIni,
                    'sudah_driver' => $sudahDriverHariIni,
                    'belum_driver' => $belumDriverHariIni,
                ],
                'muatan_bulan_ini' => [
                    'total_ton' => (float) $muatanBulanIni,
                    'jadwal'    => $jadwalBulanIni,
                ],
            ];
        });

        return $this->success($data, 'Statistik pengiriman berhasil diambil');
    }
}
