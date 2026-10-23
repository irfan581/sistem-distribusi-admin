<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class KunjunganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'toko_id'          => 'nullable|integer|exists:tokos,id',
            'nama_toko'        => 'nullable|string|max:255',
            'location'         => 'required|string|max:255',
            'foto_kunjungan'   => 'required|image|mimes:jpeg,jpg,png|max:2048',
            'keterangan'       => 'nullable|string|max:500',
            'is_mock_location' => 'nullable|in:0,1,true,false',
            'lat'              => 'nullable|numeric|between:-90,90',
            'lng'              => 'nullable|numeric|between:-180,180',
            'accuracy'         => 'nullable|numeric|min:0',
            'bypass_cooldown'  => 'nullable|boolean',
        ];
    }

    /**
     * Validasi tambahan: salah satu harus diisi (toko_id atau nama_toko)
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tokoId   = $this->input('toko_id');
            $namaToko = $this->input('nama_toko');

            if (empty($tokoId) && empty($namaToko)) {
                $validator->errors()->add('toko_id', 'Pilih toko dari daftar atau ketik nama toko manual.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'toko_id.exists'          => 'Toko yang dipilih tidak ditemukan.',
            'nama_toko.max'           => 'Nama toko maksimal 255 karakter.',
            'foto_kunjungan.required' => 'Foto bukti kunjungan belum dilampirkan.',
            'foto_kunjungan.image'    => 'File harus berupa gambar.',
            'foto_kunjungan.mimes'    => 'Foto harus format JPG atau PNG.',
            'foto_kunjungan.max'      => 'Ukuran foto maksimal 2MB.',
            'location.required'       => 'Titik koordinat GPS tidak ditemukan.',
            'lat.between'             => 'Koordinat latitude tidak valid.',
            'lng.between'             => 'Koordinat longitude tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_mock_location')) {
            $this->merge([
                'is_mock_location' => filter_var(
                    $this->is_mock_location,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? false,
            ]);
        }
    }
}
