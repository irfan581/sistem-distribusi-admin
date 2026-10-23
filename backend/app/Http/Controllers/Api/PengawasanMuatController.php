<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DaftarMuat;
use App\Models\Checker;
use Illuminate\Http\Request;

class PengawasanMuatController extends Controller
{
    /**
     * Ambil semua daftar muat per tanggal beserta checker-nya.
     */
    private function pastikanPegangNonSubsidi(Request $request): void
    {
        abort_unless(
            in_array('non_subsidi', $request->user()->jenisMuatanDiizinkan(), true),
            403,
            'Penugasan checker hanya untuk admin muat non subsidi.'
        );
    }

    public function index(Request $request)
    {
        $this->pastikanPegangNonSubsidi($request);

        $tanggal = $request->input('tanggal');

        if (!$tanggal) {
            // Default ke tanggal terakhir yang ada data non-subsidi, bukan hari ini
            $latest  = DaftarMuat::where('jenis_muatan', 'non_subsidi')
                ->latest('tanggal_muatan')->value('tanggal_muatan');
            $tanggal = $latest
                ? \Carbon\Carbon::parse($latest)->toDateString()
                : today()->toDateString();
        }

        // Checker hanya menangani muatan NON-SUBSIDI — subsidi diurus terpisah di luar sistem
        $muats = DaftarMuat::with(['driver', 'kendaraan', 'checker', 'items'])
            ->where('jenis_muatan', 'non_subsidi')
            ->whereDate('tanggal_muatan', $tanggal)
            ->orderBy('nomor_rit')
            ->get()
            ->map(function ($m) {
                return [
                    'id'               => $m->id,
                    'nomor_rit'        => $m->nomor_rit,
                    'driver'           => $m->driver?->nama_driver,
                    'kendaraan'        => $m->kendaraan?->nomor_polisi,
                    'total_muatan_ton' => $m->total_muatan_ton,
                    'checker_id'       => $m->checker_id,
                    'checker_nama'     => $m->checker?->nama,
                    'checker_aktif'    => $m->checker ? ($m->checker->status === 'Aktif') : true,
                ];
            });

        // Checker aktif untuk pilihan baru
        $checkers = Checker::where('status', 'Aktif')->orderBy('nama')->get(['id', 'nama']);

        // Sertakan checker non-aktif yang masih ter-assign di tanggal ini,
        // supaya assignment lama tidak hilang saat ditampilkan (Bug 11)
        $assignedIds = $muats->pluck('checker_id')->filter()->unique();
        $activeIds   = $checkers->pluck('id');
        $missingIds  = $assignedIds->diff($activeIds);
        if ($missingIds->isNotEmpty()) {
            $inactive = Checker::whereIn('id', $missingIds)->get(['id', 'nama'])
                ->map(fn($c) => ['id' => $c->id, 'nama' => $c->nama . ' (nonaktif)']);
            $checkers = $checkers->concat($inactive)->values();
        }

        return response()->json([
            'tanggal'  => $tanggal,
            'muats'    => $muats,
            'checkers' => $checkers,
        ]);
    }

    /**
     * Simpan assignment checker ke satu atau banyak daftar muat.
     * Body: [ { daftar_muat_id: 1, checker_id: 2 }, ... ]
     */
    public function assign(Request $request)
    {
        $this->pastikanPegangNonSubsidi($request);

        $items = $request->validate([
            'items'                  => 'required|array',
            'items.*.daftar_muat_id' => 'required|integer|exists:daftar_muats,id',
            'items.*.checker_id'     => 'nullable|integer|exists:checkers,id',
        ])['items'];

        foreach ($items as $item) {
            DaftarMuat::where('id', $item['daftar_muat_id'])
                ->where('jenis_muatan', 'non_subsidi')
                ->update(['checker_id' => $item['checker_id'] ?? null]);
        }

        return response()->json(['message' => 'Checker berhasil disimpan']);
    }
}
