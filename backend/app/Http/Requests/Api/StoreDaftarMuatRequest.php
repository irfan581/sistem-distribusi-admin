<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDaftarMuatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kendaraan_id'                  => 'nullable|exists:kendaraans,id',
            'driver_id'                     => 'nullable|exists:drivers,id',
            'tanggal_muatan'                => 'required|date',
            'total_muatan_ton'              => 'required|numeric|min:0.01',
            'items'                         => 'required|array|min:1',
            'items.*.lokasi_pengiriman'     => 'required|string|max:255',
            'items.*.kode_area'             => 'nullable|string|max:20',
        ];
    }
}
