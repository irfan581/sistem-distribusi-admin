<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KunjunganService;
use App\Http\Requests\Api\KunjunganRequest;
use App\Http\Resources\Api\KunjunganResource;
use App\Models\KunjunganToko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KunjunganController extends Controller
{
    public function __construct(protected KunjunganService $kunjunganService) {}

    public function store(KunjunganRequest $request): JsonResponse
    {
        try {
            $sales = $request->user();

            $kunjungan = $this->kunjunganService->storeKunjungan(
                $sales->id,
                $request->validated(),
                $request->file('foto_kunjungan'),
            );

            $progress = $this->kunjunganService->getProgressHariIni($sales->id);

            return response()->json([
                'success'  => true,
                'message'  => 'Kunjungan toko berhasil disimpan',
                'data'     => new KunjunganResource($kunjungan),
                'progress' => $progress,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Kunjungan store error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function checkout(Request $request, int $id): JsonResponse
    {
        $kunjungan = KunjunganToko::where('id', $id)
            ->where('sales_id', $request->user()->id)
            ->firstOrFail();

        if ($kunjungan->checkout_at) {
            return response()->json([
                'success' => false,
                'message' => 'Kunjungan ini sudah di-checkout sebelumnya.',
            ], 422);
        }

        $kunjungan->update(['checkout_at' => now()]);

        $durasi = $kunjungan->created_at->diffInSeconds($kunjungan->checkout_at);

        return response()->json([
            'success'        => true,
            'message'        => 'Checkout berhasil',
            'data'           => [
                'id'          => $kunjungan->id,
                'checkout_at' => $kunjungan->checkout_at->toIso8601String(),
                'durasi_detik'=> $durasi,
                'durasi_label'=> gmdate($durasi >= 3600 ? 'H\j i\m s\d' : 'i\m s\d', $durasi),
            ],
        ]);
    }

    public function cekCooldown(Request $request): JsonResponse
    {
        $tokoId = $request->query('toko_id');

        if (!$tokoId || !is_numeric($tokoId)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter toko_id wajib diisi.',
            ], 422);
        }

        $sales    = $request->user();
        $cooldown = $this->kunjunganService->cekCooldown($sales->id, (int) $tokoId);

        return response()->json([
            'success' => true,
            'data'    => $cooldown,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        try {
            $data = $this->kunjunganService->getRiwayat(
                $request->user()->id,
                $request->query('tanggal_mulai'),
                $request->query('tanggal_akhir'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat berhasil diambil',
                'data'    => KunjunganResource::collection($data),
            ]);
        } catch (\Exception $e) {
            Log::error('Kunjungan history error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data',
            ], 500);
        }
    }
}