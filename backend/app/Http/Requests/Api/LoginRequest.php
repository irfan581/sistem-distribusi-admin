<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nik'       => ['required', 'string'],
            'password'  => ['required', 'string'],
            'device_id' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.required'       => 'NIK wajib diisi.',
            'password.required'  => 'Password wajib diisi.',
            'device_id.required' => 'Device ID wajib diisi.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors(),
        ], 422));
    }
}