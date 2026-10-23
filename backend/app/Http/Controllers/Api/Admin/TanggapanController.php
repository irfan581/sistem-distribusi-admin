<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\KirimTanggapanFotoJob;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\KunjunganToko;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin menanggapi bukti foto dari lapangan: kunjungan sales (per kunjungan)
 * dan laporan driver (per tujuan dalam rit). Sales/driver diberi tahu lewat HP.
 */
class TanggapanController extends Controller
{
    use ApiResponse;

    public function kunjungan(Request $request, int $id): JsonResponse
    {
        $data = $this->validasi($request);
        $kunjungan = KunjunganToko::findOrFail($id);

        if ($kunjungan->tanggapi($request->user(), $data['status'], $data['catatan'] ?? null)) {
            KirimTanggapanFotoJob::dispatch($kunjungan);
        }

        return $this->success($kunjungan->load('penanggap:id,name')->tanggapanArray(), 'Tanggapan tersimpan');
    }

    public function laporan(Request $request, int $id, int $itemId): JsonResponse
    {
        $data = $this->validasi($request);
        // Admin hanya menanggapi rit jenis muatan yang ia pegang.
        $muat = DaftarMuat::bolehDipegang($request->user())->findOrFail($id);
        $item = DaftarMuatItem::where('daftar_muat_id', $muat->id)->findOrFail($itemId);

        if (! in_array($item->status_bongkar, DaftarMuatItem::STATUS_TUNTAS, true)) {
            return $this->error('Driver belum melaporkan tujuan ini.', 422);
        }

        if ($item->tanggapi($request->user(), $data['status'], $data['catatan'] ?? null)) {
            KirimTanggapanFotoJob::dispatch($item);
        }

        return $this->success($item->load('penanggap:id,name')->tanggapanArray(), 'Tanggapan tersimpan');
    }

    /** Jumlah yang belum ditanggapi, untuk penanda di menu. Hanya bagian yang boleh dilihat admin ini. */
    public function ringkasan(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'kunjungan' => $user->hasPermission('view_kunjungan') ? KunjunganToko::belumDitanggapi()->count() : 0,
            'laporan'   => $user->hasPermission('view_pengiriman')
                ? DaftarMuatItem::belumDitanggapi()
                    ->whereHas('daftarMuat', fn ($q) => $q->bolehDipegang($user))
                    ->count()
                : 0,
        ]);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'status'  => ['required', Rule::in([KunjunganToko::TANGGAPAN_DITERIMA, KunjunganToko::TANGGAPAN_DITOLAK])],
            'catatan' => ['nullable', 'string', 'max:500', 'required_if:status,' . KunjunganToko::TANGGAPAN_DITOLAK],
        ], [
            'catatan.required_if' => 'Alasan wajib diisi bila foto ditolak.',
        ]);
    }
}
