<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\LaporanTujuanRequest;
use App\Jobs\TeruskanLaporanKeGrup;
use App\Http\Resources\Driver\TugasItemResource;
use App\Http\Resources\Driver\TugasResource;
use App\Models\DaftarMuat;
use App\Services\Driver\DriverPengirimanService;
use App\Services\TujuanAlamatResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TugasController extends Controller
{
    public function __construct(
        private readonly DriverPengirimanService $pengiriman,
        private readonly TujuanAlamatResolver $alamat,
    ) {}

    public function hariIni(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $hariIni = now()->timezone('Asia/Jakarta')->toDateString();

        // Tanpa parameter tanggal, beranda mendapat rit yang sedang jadi
        // tanggung jawabnya — penentunya nama sopir yang sudah dimasukkan
        // admin, bukan tanggal persis hari ini. Dengan tanggal, perilakunya
        // tetap seperti dulu supaya pemanggil lama tidak berubah artinya.
        $tugas = isset($data['tanggal'])
            ? $this->pengiriman->tugasHarian($request->user(), $data['tanggal'])
            : $this->pengiriman->tugasAktif($request->user());

        $tanggal = $data['tanggal'] ?? $hariIni;

        foreach ($tugas as $rit) {
            $this->alamat->resolveBanyak($rit->items);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tugas pengiriman',
            'data'    => [
                'tanggal' => $tanggal,
                'rit'     => TugasResource::collection($tugas),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $muat = DaftarMuat::with(array_merge(
            $this->pengiriman->relasiTampilan(),
            ['logs.driver', 'logs.user'],
        ))->findOrFail($id);

        // Driver hanya boleh melihat ritnya sendiri.
        abort_if($muat->driver_id !== $request->user()->id, 403, 'Rit ini bukan tugas Anda.');

        $this->alamat->resolveBanyak($muat->items);

        return response()->json([
            'success' => true,
            'message' => 'Detail tugas',
            'data'    => new TugasResource($muat),
        ]);
    }

    /**
     * Riwayat rit driver, dipaginasi dan terbaru lebih dulu.
     *
     * Tanpa `dari`, seluruh riwayat ikut tampil — kantor ingin kiriman lama
     * tetap bisa dilihat sopir. Paginasi yang menjaga halaman pertama tetap ringan.
     */
    public function riwayat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari'     => ['nullable', 'date'],
            'sampai'   => ['nullable', 'date', 'after_or_equal:dari'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $sampai = isset($data['sampai'])
            ? Carbon::parse($data['sampai'])
            : now()->timezone('Asia/Jakarta');

        $dari = isset($data['dari']) ? Carbon::parse($data['dari']) : null;

        $halaman = $this->pengiriman->riwayat(
            $request->user(),
            $dari?->toDateString(),
            $sampai->toDateString(),
            $data['per_page'] ?? 15,
        );

        foreach ($halaman as $rit) {
            $this->alamat->resolveBanyak($rit->items);
        }

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pengiriman',
            'data'    => [
                'periode' => [
                    'dari'   => $dari?->toDateString(),
                    'sampai' => $sampai->toDateString(),
                ],
                'rit'        => TugasResource::collection($halaman->items()),
                'pagination' => [
                    'current_page' => $halaman->currentPage(),
                    'last_page'    => $halaman->lastPage(),
                    'per_page'     => $halaman->perPage(),
                    'total'        => $halaman->total(),
                ],
            ],
        ]);
    }

    /**
     * Ringkasan kerja driver untuk beranda aplikasi.
     *
     * Rentang bawaan 30 hari terakhir. Dibatasi setahun supaya satu permintaan
     * tidak menyapu seluruh riwayat driver lama.
     */
    public function ringkasan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari'   => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $sampai = isset($data['sampai'])
            ? Carbon::parse($data['sampai'])
            : now()->timezone('Asia/Jakarta');

        $dari = isset($data['dari'])
            ? Carbon::parse($data['dari'])
            : $sampai->copy()->subDays(29);

        if ($dari->diffInDays($sampai) > 366) {
            $dari = $sampai->copy()->subDays(366);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan pengiriman',
            'data'    => $this->pengiriman->ringkasan(
                $request->user(),
                $dari->toDateString(),
                $sampai->toDateString(),
            ),
        ]);
    }

    public function simpanLaporan(LaporanTujuanRequest $request, int $id, int $itemId): JsonResponse
    {
        $item = $this->pengiriman->simpanLaporan(
            $request->user(),
            $id,
            $itemId,
            $request->string('status')->toString(),
            $request->input('catatan'),
            $request->input('latitude'),
            $request->input('longitude'),
        );

        // Diteruskan ke grup chat setelah transaksi simpanLaporan selesai,
        // supaya laporan yang gagal tersimpan tidak pernah terlanjur muncul di
        // grup. Yang selesai maupun yang bermasalah sama-sama dikirim: di grup
        // WhatsApp yang digantikannya, sopir juga mengabarkan dua-duanya.
        //
        // Dibungkus karena laporan driver TIDAK BOLEH gagal gara-gara chat.
        // Di lokal antriannya 'sync', jadi seluruh isi job berjalan di dalam
        // dispatch() ini — satu galat di sana akan membuat sopir yang sedang
        // berdiri di depan toko tidak bisa menutup tujuannya, lalu mengirim
        // ulang berkali-kali. Buktinya sudah tersimpan; sampainya ke grup
        // boleh tertinggal.
        try {
            TeruskanLaporanKeGrup::dispatch($item->id, $request->user()->id);
        } catch (\Throwable $e) {
            Log::warning("Laporan tujuan {$item->id} gagal diteruskan ke grup: {$e->getMessage()}");
        }

        return response()->json([
            'success' => true,
            'message' => $item->status_bongkar === 'Selesai'
                ? 'Laporan tersimpan'
                : 'Laporan masalah tersimpan. Hubungi kantor.',
            'data'    => new TugasItemResource($item->loadCount('bongkarFotos')),
        ]);
    }
}
