<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\LoginRequest;
use App\Http\Resources\Driver\DriverResource;
use App\Services\Driver\DriverAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly DriverAuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $hasil = $this->auth->login(
            $request->string('kode_driver')->toString(),
            $request->string('password')->toString(),
            $request->input('device_id'),
            $request->input('fcm_token'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data'    => [
                'token'                => $hasil['token'],
                // Profil di aplikasi dibangun dari respons ini, jadi kendaraan tetap harus ikut.
                'driver'               => new DriverResource($hasil['driver']->load('kendaraans')),
                'harus_ganti_password' => (bool) $hasil['driver']->harus_ganti_password,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $driver = $request->user();

        $driver->currentAccessToken()->delete();

        // HP yang sudah keluar tidak lagi menerima notifikasi akun ini.
        $driver->forceFill(['fcm_token' => null])->save();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
            'data'    => null,
        ]);
    }
}
