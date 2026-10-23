<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'       => ['required', 'string', 'min:3', 'max:100'],
            'deskripsi'  => ['nullable', 'string', 'max:255'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer'],
            'member_types' => ['required', 'array', 'min:1'],
            'member_types.*' => ['string', 'in:user,sales'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required'        => 'Nama grup wajib diisi',
            'nama.min'             => 'Nama grup minimal 3 karakter',
            'member_ids.required'  => 'Minimal 1 anggota harus dipilih',
            'member_ids.min'       => 'Minimal 1 anggota harus dipilih',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
