<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;

class UnggahFotoBongkarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'foto'         => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:8192'],
            // Dibuat di HP sebelum kirim. Menjadi kunci idempotensi supaya
            // antrian offline boleh mengulang unggahan tanpa foto ganda.
            'client_uuid'  => ['required', 'uuid'],
            'jenis'        => ['nullable', 'string', 'in:bongkar,surat_jalan,kendala'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy'     => ['nullable', 'numeric', 'min:0'],
            'is_mock'      => ['nullable', 'boolean'],
            // Jam menurut HP driver; disimpan sebagai klaim, bukan kebenaran.
            'diambil_pada' => ['nullable', 'date'],
            'catatan'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.required'        => 'Fotonya belum ikut terkirim.',
            'foto.image'           => 'Berkas yang dikirim bukan gambar.',
            'foto.max'             => 'Ukuran foto melebihi 8 MB.',
            'client_uuid.required' => 'Kode foto tidak ada. Perbarui aplikasi.',
            'jenis.in'             => 'Jenis foto hanya bisa bongkar, surat_jalan, atau kendala.',
        ];
    }
}
