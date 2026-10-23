<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\IzinService;
use App\Http\Requests\Api\IzinRequest;
use App\Http\Resources\Api\IzinResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IzinController extends Controller
{
    protected $izinService;

    public function __construct(IzinService $izinService)
    {
        $this->izinService = $izinService;
    }

    public function riwayat(Request $request): JsonResponse
    {
        try {
            $sales = $request->user();

            $riwayat = $this->izinService->getRiwayatIzin(
                $sales->id,
                $request->query('status'),
                $request->query('start_date'),
                $request->query('end_date'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat berhasil diambil',
                'data'    => IzinResource::collection($riwayat),
            ]);
        } catch (\Exception $e) {
            Log::error('Izin riwayat error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data riwayat izin.',
            ], 500);
        }
    }

    public function ajukanIzin(IzinRequest $request): JsonResponse
    {
        Log::info('IZIN HIT');
        Log::info($request->all());
        Log::info(['file' => $request->file('bukti_foto')]);

        try {
            $validatedData = $request->validated();
            $validatedData['sales_id'] = $request->user()->id;

            $izin = $this->izinService->ajukanIzin(
                $validatedData,
                $request->file('bukti_foto')
            );

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil dikirim',
                'data'    => new IzinResource($izin),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Izin ajukan error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan izin. Coba lagi.',
            ], 500);
        }
    }

    public function cekIzinHariIni(Request $request): JsonResponse
    {
        try {
            $sales     = $request->user();
            $izinAktif = $this->izinService->getIzinAktif($sales->id);
            $cekBoleh  = $this->izinService->cekBolehAbsenMasuk($sales->id);

            return response()->json([
                'success'     => true,
                'izin_aktif'  => $izinAktif ? new IzinResource($izinAktif) : null,
                'boleh_absen' => $cekBoleh['boleh'],
                'keterangan'  => $cekBoleh['alasan'],
            ]);
        } catch (\Exception $e) {
            Log::error('Izin cek hari ini error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek izin.',
            ], 500);
        }
    }

    /**
     * Batalkan/hapus izin sendiri — hanya boleh jika masih pending
     */
    public function batalIzin(Request $request, int $id): JsonResponse
    {
        try {
            $sales = $request->user();
            $izin  = \App\Models\Izin::where('id', $id)
                ->where('sales_id', $sales->id)
                ->firstOrFail();

            if ($izin->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Izin yang sudah diproses tidak dapat dibatalkan.',
                ], 422);
            }

            $izin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Izin berhasil dibatalkan.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data izin tidak ditemukan.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Izin batal error', ['sales_id' => $request->user()?->id, 'izin_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan izin.',
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        return $this->riwayat($request);
    }

    public function store(IzinRequest $request): JsonResponse
    {
        return $this->ajukanIzin($request);
    }
}
