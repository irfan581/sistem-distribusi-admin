<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_driver' => ['required', 'string', 'max:20'],
            'password'    => ['required', 'string'],
            'device_id'   => ['nullable', 'string', 'max:255'],
            'fcm_token'   => ['nullable', 'string', 'max:255'],
            'versi_app'   => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode_driver.required' => 'Kode driver wajib diisi.',
            'password.required'    => 'Password wajib diisi.',
        ];
    }
}
