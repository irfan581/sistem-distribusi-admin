<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BongkarFoto;
use App\Models\drivers;
use App\Services\Driver\DriverAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(private readonly DriverAuthService $auth) {}

    public function index(Request $request): JsonResponse
    {
        $query = drivers::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_driver', 'like', "%{$request->search}%")
                  ->orWhere('no_telepon', 'like', "%{$request->search}%")
                  ->orWhere('kode_driver', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status_kerja', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('nama_driver')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_driver'    => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:50',
            'no_telepon'     => 'required|string|max:20',
            'alamat'         => 'required|string',
            'status_kerja'   => 'required|in:Aktif,Tidak Aktif',
            'jenis_sim'      => 'nullable|in:A,B1,B1 Umum,B2,B2 Umum',
        ]);

        // Kode wajib ada sejak driver dibuat — ini identitas login aplikasi.
        // Akun mobilenya sendiri baru hidup setelah admin mengaktifkannya.
        $data['kode_driver'] = $this->auth->generateKode();

        $driver = drivers::create($data);

        return response()->json(['success' => true, 'data' => $driver], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $driver = drivers::findOrFail($id);

        $data = $request->validate([
            'nama_driver'    => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:50',
            'no_telepon'     => 'required|string|max:20',
            'alamat'         => 'required|string',
            'status_kerja'   => 'required|in:Aktif,Tidak Aktif',
            'jenis_sim'      => 'nullable|in:A,B1,B1 Umum,B2,B2 Umum',
        ]);

        $driver->update($data);

        // Driver yang berhenti bekerja tidak boleh lagi membuka aplikasi.
        if ($driver->status_kerja !== 'Aktif') {
            $this->putuskanPerangkat($driver);
        }

        return response()->json(['success' => true, 'data' => $driver->refresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $driver = drivers::findOrFail($id);

        // Rit dan foto bukti ikut terhapus lewat ON DELETE CASCADE, jadi riwayat dijaga di sini.
        if ($driver->daftarMuats()->exists() || BongkarFoto::where('driver_id', $driver->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Driver ini sudah punya riwayat pengiriman, jadi tidak bisa dihapus. Ubah status kerjanya menjadi Tidak Aktif agar riwayat tetap tersimpan.',
            ], 422);
        }

        $driver->tokens()->delete();
        $driver->delete();

        return response()->json(['success' => true, 'message' => 'Driver berhasil dihapus']);
    }

    /**
     * Menghidupkan akun aplikasi driver dan mengembalikan password sementara.
     * Password polos hanya muncul di respons ini, tidak pernah bisa dilihat lagi.
     */
    public function aktifkanAkun(int $id): JsonResponse
    {
        $driver = drivers::findOrFail($id);

        if ($driver->status_kerja !== 'Aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Driver berstatus tidak aktif. Ubah status kerjanya dulu sebelum mengaktifkan akun aplikasi.',
            ], 422);
        }

        if (empty($driver->kode_driver)) {
            $driver->kode_driver = $this->auth->generateKode();
        }

        $driver->mobile_aktif = true;
        $driver->save();

        $password = $this->auth->resetPassword($driver);

        return response()->json([
            'success' => true,
            'message' => 'Akun aplikasi driver berhasil diaktifkan',
            'data'    => [
                'kode_driver'        => $driver->kode_driver,
                'password_sementara' => $password,
            ],
        ]);
    }

    public function resetPassword(int $id): JsonResponse
    {
        $driver = drivers::findOrFail($id);

        $password = $this->auth->resetPassword($driver);

        return response()->json([
            'success' => true,
            'message' => 'Password driver berhasil direset',
            'data'    => [
                'kode_driver'        => $driver->kode_driver,
                'password_sementara' => $password,
            ],
        ]);
    }

    public function nonaktifkanAkun(int $id): JsonResponse
    {
        $driver = drivers::findOrFail($id);

        $driver->mobile_aktif = false;
        $driver->save();
        $this->putuskanPerangkat($driver);

        return response()->json([
            'success' => true,
            'message' => 'Akun aplikasi driver dinonaktifkan',
            'data'    => null,
        ]);
    }

    /** Mencabut sesi dan melupakan HP driver, sesuai janji di halaman hapus akun. */
    private function putuskanPerangkat(drivers $driver): void
    {
        $driver->tokens()->delete();
        $driver->forceFill(['device_id' => null, 'fcm_token' => null])->save();
    }
}
