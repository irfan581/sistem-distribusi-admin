<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatPollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pertanyaan'      => ['required', 'string', 'min:5', 'max:255'],
            'pilihan'         => ['required', 'array', 'min:2', 'max:10'],
            'pilihan.*'       => ['required', 'string', 'max:100'],
            'multiple_choice' => ['boolean'],
            'tutup_pada'      => ['nullable', 'date', 'after:now'],
            'type'            => ['required', 'in:dm,group'],
            'group_id'        => ['required_if:type,group', 'nullable', 'integer'],
            'receiver_type'   => ['required_if:type,dm', 'nullable', 'in:user,sales'],
            'receiver_id'     => ['required_if:type,dm', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'pertanyaan.required' => 'Pertanyaan polling wajib diisi',
            'pertanyaan.min'      => 'Pertanyaan minimal 5 karakter',
            'pilihan.required'    => 'Pilihan jawaban wajib diisi',
            'pilihan.min'         => 'Minimal 2 pilihan jawaban',
            'pilihan.max'         => 'Maksimal 10 pilihan jawaban',
            'pilihan.*.required'  => 'Setiap pilihan tidak boleh kosong',
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
