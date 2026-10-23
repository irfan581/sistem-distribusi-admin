<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProfileRequest;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Services\ProfileService;
use App\Http\Resources\Api\SalesResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct(protected ProfileService $profileService) {}

    public function getProfile(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new SalesResource($request->user()),
        ]);
    }

    public function updateProfile(ProfileRequest $request): JsonResponse
    {
        try {
            $updatedUser = $this->profileService->update(
                $request->user(),
                $request->validated(),
                $request->file('foto_profil'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Profil berhasil diperbarui',
                'data'    => new SalesResource($updatedUser),
            ]);
        } catch (\Exception $e) {
            Log::error('Update profil error', ['sales_id' => $request->user()->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal update profil',
            ], 500);
        }
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $this->profileService->updatePassword($request->user(), $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Password berhasil diperbarui',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(), // aman — pesan bisnis dari Service
            ], 400);
        }
    }

    // ✅ Reset password — tidak kirim password ke response
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $this->profileService->resetPassword($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Password berhasil direset ke NIK',
                // ✅ Tidak kirim password_baru ke response
            ]);
        } catch (\Exception $e) {
            Log::error('Reset password error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal reset password',
            ], 500);
        }
    }
}
