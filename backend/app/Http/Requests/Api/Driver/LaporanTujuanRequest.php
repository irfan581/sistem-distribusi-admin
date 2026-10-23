<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;

class LaporanTujuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'    => ['required', 'string', 'in:Selesai,Bermasalah'],
            // Wajib untuk kedua jalan keluar, bukan cuma saat bermasalah.
            // Diperiksa lagi di service supaya penolakannya membawa kode
            // CATATAN_WAJIB seperti aturan lapangan yang lain.
            'catatan'   => ['required', 'string', 'max:255'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'        => 'Laporan tujuan hanya bisa Selesai atau Bermasalah.',
            'catatan.required' => 'Keterangan wajib diisi.',
        ];
    }
}
