<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Berhasil', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function created(mixed $data = null, string $message = 'Data berhasil dibuat'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $message = 'Terjadi kesalahan', int $code = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Tidak diizinkan'): JsonResponse
    {
        return $this->error($message, 401);
    }

    protected function forbidden(string $message = 'Akses ditolak'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function validationError(mixed $errors, string $message = 'Validasi gagal'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], 422);
    }
}
