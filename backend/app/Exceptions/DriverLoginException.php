<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kegagalan autentikasi driver yang sudah membawa kode dan status HTTP-nya
 * sendiri, supaya aplikasi Flutter bisa membedakan "password salah" dari
 * "akun belum diaktifkan" tanpa mencocokkan teks pesan.
 */
class DriverLoginException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $kode,
        public readonly int $status = 401,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code'    => $this->kode,
        ], $this->status);
    }
}
