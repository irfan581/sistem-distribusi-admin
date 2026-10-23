<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\kendaraans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KendaraanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = kendaraans::with('driver');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nomor_polisi', 'like', "%{$request->search}%")
                  ->orWhere('merk', 'like', "%{$request->search}%")
                  ->orWhere('jenis_kendaraan', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_kendaraan', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('nomor_polisi')->get(),
        ]);
    }

    private array $messages = [
        'jenis_kendaraan.required'      => 'Jenis kendaraan wajib diisi.',
        'nomor_polisi.required'         => 'Nomor polisi wajib diisi.',
        'nomor_polisi.unique'           => 'Nomor polisi sudah terdaftar dalam sistem.',
        'nomor_polisi.max'              => 'Nomor polisi maksimal 20 karakter.',
        'merk.required'                 => 'Merk wajib diisi.',
        'tahun_pembuatan.required'      => 'Tahun pembuatan wajib diisi.',
        'tahun_pembuatan.integer'       => 'Tahun pembuatan harus berupa angka.',
        'tahun_pembuatan.min'           => 'Tahun pembuatan minimal 1990.',
        'tahun_pembuatan.max'           => 'Tahun pembuatan maksimal 2030.',
        'kapasitas_muatan_ton.required' => 'Kapasitas muatan wajib diisi.',
        'kapasitas_muatan_ton.numeric'  => 'Kapasitas muatan harus berupa angka.',
        'kapasitas_muatan_ton.min'      => 'Kapasitas muatan minimal 0.5 ton.',
        'driver_id.exists'              => 'Driver yang dipilih tidak valid.',
        'status_kendaraan.required'     => 'Status kendaraan wajib diisi.',
        'status_kendaraan.in'           => 'Status kendaraan tidak valid.',
    ];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis_kendaraan'      => 'required|string|max:100',
            'nomor_polisi'         => 'required|string|max:20|unique:kendaraans,nomor_polisi',
            'merk'                 => 'required|string|max:100',
            'model'                => 'nullable|string|max:100',
            'tahun_pembuatan'      => 'required|integer|min:1990|max:2030',
            'kapasitas_muatan_ton' => 'required|numeric|min:0.5',
            'driver_id'            => 'nullable|exists:drivers,id',
            'status_kendaraan'     => 'required|in:Tersedia,Tidak Tersedia',
        ], $this->messages);

        $kendaraan = kendaraans::create($data);

        return response()->json(['success' => true, 'data' => $kendaraan->load('driver')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $kendaraan = kendaraans::findOrFail($id);

        $data = $request->validate([
            'jenis_kendaraan'      => 'required|string|max:100',
            'nomor_polisi'         => "required|string|max:20|unique:kendaraans,nomor_polisi,{$id}",
            'merk'                 => 'required|string|max:100',
            'model'                => 'nullable|string|max:100',
            'tahun_pembuatan'      => 'required|integer|min:1990|max:2030',
            'kapasitas_muatan_ton' => 'required|numeric|min:0.5',
            'driver_id'            => 'nullable|exists:drivers,id',
            'status_kendaraan'     => 'required|in:Tersedia,Tidak Tersedia',
        ], $this->messages);

        $kendaraan->update($data);

        return response()->json(['success' => true, 'data' => $kendaraan->load('driver')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $kendaraan = kendaraans::findOrFail($id);
        $kendaraan->delete();

        return response()->json(['success' => true, 'message' => 'Kendaraan berhasil dihapus']);
    }
}
