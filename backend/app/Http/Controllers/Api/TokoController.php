<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Toko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $sales = $request->user();

            $query = Toko::select(['id', 'nama_kios', 'wilayah', 'sales_id'])
                ->orderBy('nama_kios');

            // SPV bisa lihat semua toko
            if (!$sales->is_spv) {
                $query->where('sales_id', $sales->id);
            }

            $tokos = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Data toko berhasil diambil',
                'data'    => $tokos,
            ]);
        } catch (\Exception $e) {
            Log::error('TokoController index error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data toko',
            ], 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $sales = $request->user();
            $keyword = $request->query('q', '');

            $query = Toko::select(['id', 'nama_kios', 'wilayah', 'sales_id'])
                ->where('nama_kios', 'like', "%{$keyword}%")
                ->orderBy('nama_kios')
                ->limit(50);

            // SPV bisa search semua toko
            if (!$sales->is_spv) {
                $query->where('sales_id', $sales->id);
            }

            $tokos = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Hasil pencarian toko',
                'data'    => $tokos,
            ]);
        } catch (\Exception $e) {
            Log::error('TokoController search error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencari toko',
            ], 500);
        }
    }
}
