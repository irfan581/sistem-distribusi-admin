<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverIzin;
use App\Models\drivers;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DriverIzinController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = $request->input('tanggal', today()->toDateString());

        $izin = DriverIzin::with('driver')
            ->whereDate('tanggal', $tanggal)
            ->orderBy('keterangan')
            ->get()
            ->map(fn($i) => [
                'id'          => $i->id,
                'driver_id'   => $i->driver_id,
                'nama_driver' => $i->driver?->nama_driver,
                'keterangan'  => $i->keterangan,
                'catatan'     => $i->catatan,
                'tanggal'     => $i->tanggal->toDateString(),
            ]);

        return response()->json(['data' => $izin]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'driver_id'  => 'required|exists:drivers,id',
            'tanggal'    => 'required|date',
            'keterangan' => 'required|in:Sakit,Cuti,Izin,Lainnya',
            'catatan'    => 'nullable|string|max:200',
        ]);

        $izin = DriverIzin::updateOrCreate(
            ['driver_id' => $data['driver_id'], 'tanggal' => $data['tanggal']],
            ['keterangan' => $data['keterangan'], 'catatan' => $data['catatan'] ?? null]
        );

        return response()->json([
            'message' => 'Data izin disimpan.',
            'data'    => $izin,
        ], 201);
    }

    public function destroy($id)
    {
        DriverIzin::findOrFail($id)->delete();
        return response()->json(['message' => 'Data izin dihapus.']);
    }

    public function drivers()
    {
        return response()->json([
            'data' => drivers::where('status_kerja', 'Aktif')
                ->orderBy('nama_driver')
                ->get(['id', 'nama_driver', 'no_telepon']),
        ]);
    }
}
