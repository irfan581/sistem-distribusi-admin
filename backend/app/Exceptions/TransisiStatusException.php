<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perpindahan status pengiriman yang tidak sah. Membawa kode sendiri supaya
 * aplikasi driver bisa menampilkan pesan yang tepat tanpa mencocokkan teks.
 */
class TransisiStatusException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $kode,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code'    => $this->kode,
        ], 422);
    }
}
