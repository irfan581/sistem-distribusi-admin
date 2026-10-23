<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;

class GantiPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password_lama' => ['required', 'string'],
            'password_baru' => ['required', 'string', 'min:8', 'max:64', 'confirmed', 'different:password_lama'],
        ];
    }

    public function messages(): array
    {
        return [
            'password_baru.min'       => 'Password baru minimal 8 karakter.',
            'password_baru.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password_baru.different' => 'Password baru harus berbeda dari password lama.',
        ];
    }
}
