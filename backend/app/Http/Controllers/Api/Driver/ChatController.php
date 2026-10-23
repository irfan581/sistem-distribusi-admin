<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Api\ChatController as ChatBersama;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pintu chat untuk aplikasi sopir.
 *
 * Logikanya tidak ditulis ulang — seluruh metode di ChatController sudah
 * polimorfik (memakai get_class($user)), jadi kelas ini hanya meneruskan
 * panggilan lalu menyeragamkan bentuk responsnya.
 *
 * Penyeragaman itu bukan kerapian belaka: seluruh endpoint driver membalas
 * {success, message, data} dan ServisApi di aplikasi sopir membaca kunci
 * `success`. Chat bersama membalas {status: "success"}, yang akan terbaca
 * sebagai kegagalan di sana. Menerjemahkannya di sini lebih aman daripada
 * melonggarkan ServisApi supaya menerima dua bentuk — pelonggaran itu
 * membuat respons yang benar-benar gagal ikut lolos.
 */
class ChatController extends Controller
{
    public function __construct(private readonly ChatBersama $chat) {}

    public function grup(Request $request): JsonResponse
    {
        return $this->seragamkan($this->chat->myGroups($request), 'Daftar grup');
    }

    public function pesan(Request $request, int $groupId): JsonResponse
    {
        return $this->seragamkan($this->chat->groupMessages($request, $groupId), 'Pesan grup');
    }

    public function kirim(Request $request): JsonResponse
    {
        return $this->seragamkan($this->chat->send($request), 'Pesan terkirim');
    }

    public function unread(Request $request): JsonResponse
    {
        return $this->seragamkan($this->chat->unreadCount($request), 'Jumlah belum dibaca');
    }

    private function seragamkan(JsonResponse $asli, string $pesanBawaan): JsonResponse
    {
        $isi    = $asli->getData(true);
        $sukses = ($isi['status'] ?? null) === 'success';

        return response()->json([
            'success' => $sukses,
            'message' => $isi['message'] ?? ($sukses ? $pesanBawaan : 'Gagal memuat'),
            'data'    => $isi['data'] ?? null,
        ], $asli->getStatusCode());
    }
}
