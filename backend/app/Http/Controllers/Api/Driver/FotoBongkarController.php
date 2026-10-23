<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\UnggahFotoBongkarRequest;
use App\Http\Resources\Driver\BongkarFotoResource;
use App\Services\Driver\BongkarFotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FotoBongkarController extends Controller
{
    public function __construct(private readonly BongkarFotoService $fotoBongkar) {}

    public function simpan(UnggahFotoBongkarRequest $request, int $id, int $itemId): JsonResponse
    {
        $foto = $this->fotoBongkar->simpan(
            $request->user(),
            $id,
            $itemId,
            $request->file('foto'),
            $request->safe()->except('foto'),
        );

        // 200 saat kiriman ulang dari antrian offline dijawab dengan foto yang
        // sudah ada, 201 saat benar-benar baru masuk.
        return response()->json([
            'success' => true,
            'message' => $foto->wasRecentlyCreated ? 'Foto tersimpan' : 'Foto ini sudah diterima sebelumnya',
            'data'    => new BongkarFotoResource($foto),
        ], $foto->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request, int $id, int $itemId): JsonResponse
    {
        $fotos = $this->fotoBongkar->daftar($request->user(), $id, $itemId);

        return response()->json([
            'success' => true,
            'message' => 'Daftar foto tujuan',
            'data'    => BongkarFotoResource::collection($fotos),
        ]);
    }

    public function hapus(Request $request, int $id, int $itemId, int $fotoId): JsonResponse
    {
        $this->fotoBongkar->hapus($request->user(), $id, $itemId, $fotoId);

        return response()->json([
            'success' => true,
            'message' => 'Foto dihapus',
        ]);
    }
}
