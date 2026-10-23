<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sales;
use App\Support\Berkas;
use App\Traits\ApiResponse;
use App\Traits\UploadGambar;
use App\Models\Presensi;
use App\Models\KunjunganToko;
use App\Models\Izin;
use App\Models\Toko;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class SalesController extends Controller
{
    use ApiResponse, UploadGambar;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nik'              => 'required|string|max:50|unique:sales,nik',
            'nama'             => 'required|string|max:255',
            'no_hp'            => 'required|string|max:20',
            'area'             => 'nullable|string|max:100',
            'alamat'           => 'nullable|string',
            'password'         => 'required|string|min:6|confirmed',
            'target_kunjungan' => 'nullable|integer|min:0',
            'is_active'        => 'boolean',
            'is_spv'           => 'boolean',
            'foto_profil'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Upload foto jika ada
        if ($request->hasFile('foto_profil')) {
            $data['foto_profil'] = $this->uploadCompressed($request->file('foto_profil'), 'foto-sales');
        }

        $sales = Sales::create($data);

        // Setiap sales otomatis punya entri toko "KANTOR" di master data,
        // supaya muncul langsung di picker toko app tanpa perlu update app.
        Toko::create([
            'sales_id'  => $sales->id,
            'nama_kios' => 'KANTOR',
            'jenis'     => Toko::JENIS_KANTOR,
            'wilayah'   => $sales->area ?? 'Kantor',
            'is_new'    => false,
        ]);

        return $this->success($this->format($sales), 'Akun sales berhasil dibuat', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $sales = Sales::findOrFail($id);

        $data = $request->validate([
            'nik'              => 'required|string|max:50|unique:sales,nik,' . $id,
            'nama'             => 'required|string|max:255',
            'no_hp'            => 'required|string|max:20',
            'area'             => 'nullable|string|max:100',
            'alamat'           => 'nullable|string',
            'target_kunjungan' => 'nullable|integer|min:0',
            'is_active'        => 'boolean',
            'is_spv'           => 'boolean',
            'foto_profil'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Upload foto baru, hapus foto lama jika ada
        if ($request->hasFile('foto_profil')) {
            Berkas::hapus($sales->foto_profil);
            $data['foto_profil'] = $this->uploadCompressed($request->file('foto_profil'), 'foto-sales');
        }

        $sales->update($data);

        return $this->success($this->format($sales->fresh()), 'Data sales berhasil diperbarui');
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $sales = Sales::findOrFail($id);

        $request->validate([
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $sales->update(['password' => $request->password]);

        return $this->success(null, 'Password sales berhasil direset');
    }

    public function resetDevice(int $id): JsonResponse
    {
        $sales = Sales::findOrFail($id);
        $sales->updateQuietly(['device_id' => null]);

        return $this->success(null, 'Device ID sales berhasil direset');
    }

    public function downloadReport(Request $request, int $id): Response
    {
        $sales = Sales::findOrFail($id);

        $request->validate([
            'dari'   => 'required|date',
            'sampai' => 'required|date|after_or_equal:dari',
            'tipe'   => 'nullable|array',
            'tipe.*' => 'in:absen,toko,izin',
        ]);

        $dari   = Carbon::parse($request->dari)->toDateString();
        $sampai = Carbon::parse($request->sampai)->toDateString();
        $tipe   = $request->input('tipe', ['absen', 'toko', 'izin']);

        $combined     = collect();
        $totalHadir   = 0;
        $totalKunjungan = 0;

        if (in_array('absen', $tipe)) {
            $presensi = Presensi::where('sales_id', $id)
                ->whereBetween('tanggal', [$dari, $sampai])
                ->get();

            $totalHadir = $presensi->count();

            foreach ($presensi as $p) {
                $tgl = Carbon::parse($p->tanggal)->toDateString();
                $combined->push([
                    'waktu'      => $tgl . ' ' . ($p->jam_masuk ?? '00:00'),
                    'type'       => 'Absen',
                    'detail'     => 'MASUK ' . ($p->status === 'terlambat' ? '(Telat)' : '(On Time)'),
                    'location'   => $p->location_masuk ?? '-',
                    'keterangan' => $p->keterangan ?? '-',
                    'sort_key'   => Carbon::parse($tgl . ' ' . ($p->jam_masuk ?? '00:00')),
                ]);
                if ($p->jam_pulang) {
                    $combined->push([
                        'waktu'      => $tgl . ' ' . $p->jam_pulang,
                        'type'       => 'Absen',
                        'detail'     => 'PULANG',
                        'location'   => $p->location_pulang ?? '-',
                        'keterangan' => 'Selesai Tugas',
                        'sort_key'   => Carbon::parse($tgl . ' ' . $p->jam_pulang),
                    ]);
                }
            }
        }

        if (in_array('toko', $tipe)) {
            $kunjungan = KunjunganToko::where('sales_id', $id)
                ->whereBetween('created_at', [
                    Carbon::parse($dari)->startOfDay(),
                    Carbon::parse($sampai)->endOfDay(),
                ])
                ->get();

            $totalKunjungan = $kunjungan->count();

            foreach ($kunjungan as $k) {
                $combined->push([
                    'waktu'      => $k->created_at->format('Y-m-d H:i:s'),
                    'type'       => 'Toko',
                    'detail'     => 'Kunjungan: ' . ($k->nama_toko ?? 'Toko'),
                    'location'   => $k->location ?? '-',
                    'keterangan' => $k->keterangan ?? '-',
                    'sort_key'   => $k->created_at,
                ]);
            }
        }

        if (in_array('izin', $tipe)) {
            Izin::where('sales_id', $id)
                ->where('status', 'disetujui')
                ->whereBetween('tanggal', [$dari, $sampai])
                ->get()
                ->each(function ($i) use ($combined) {
                    $combined->push([
                        'waktu'      => Carbon::parse($i->tanggal)->format('Y-m-d'),
                        'type'       => 'Izin',
                        'detail'     => 'IZIN: ' . strtoupper($i->jenis_izin ?? 'IZIN'),
                        'location'   => '-',
                        'keterangan' => $i->keterangan ?? '-',
                        'sort_key'   => Carbon::parse($i->tanggal)->startOfDay(),
                    ]);
                });
        }

        $activities = $combined->sortBy('sort_key')->values();

        $pdf = Pdf::loadView('pdf.recap-presensi', [
            'sales'           => $sales,
            'activities'      => $activities,
            'total_hadir'     => $totalHadir,
            'total_kunjungan' => $totalKunjungan,
            'date_range'      => Carbon::parse($dari)->format('d/m/Y') . ' - ' . Carbon::parse($sampai)->format('d/m/Y'),
            'printed_by'      => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        $filename = "Rekap_{$sales->nama}_" . date('Ymd') . ".pdf";

        return $pdf->download($filename);
    }

    public function destroy(int $id): JsonResponse
    {
        Sales::findOrFail($id)->delete();
        return $this->success(null, 'Akun sales berhasil dihapus');
    }

    private function format(Sales $s): array
    {
        return [
            'id'               => $s->id,
            'nik'              => $s->nik,
            'nama'             => $s->nama,
            'no_hp'            => $s->no_hp,
            'area'             => $s->area,
            'alamat'           => $s->alamat,
            'target_kunjungan' => $s->target_kunjungan,
            'is_active'        => $s->is_active,
            'is_spv'           => $s->is_spv,
            'foto_profil_url'  => $s->foto_profil_url,
        ];
    }
}
