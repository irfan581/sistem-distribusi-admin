<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\GantiPasswordRequest;
use App\Http\Resources\Driver\DriverResource;
use App\Services\Driver\DriverAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilController extends Controller
{
    public function __construct(private readonly DriverAuthService $auth) {}

    public function show(Request $request): JsonResponse
    {
        $driver = $request->user()->load('kendaraans');

        return response()->json([
            'success' => true,
            'message' => 'Profil driver',
            'data'    => new DriverResource($driver),
        ]);
    }

    public function gantiPassword(GantiPasswordRequest $request): JsonResponse
    {
        $this->auth->gantiPassword(
            $request->user(),
            $request->string('password_lama')->toString(),
            $request->string('password_baru')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah',
            'data'    => null,
        ]);
    }

    public function simpanFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->forceFill(['fcm_token' => $data['fcm_token']])->save();

        return response()->json([
            'success' => true,
            'message' => 'Token notifikasi tersimpan',
            'data'    => null,
        ]);
    }
}
