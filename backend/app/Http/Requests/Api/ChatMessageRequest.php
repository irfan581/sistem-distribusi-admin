<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'          => ['required', 'in:dm,group'],
            'message'       => ['required_without:file', 'nullable', 'string', 'max:1000'],
            'file'          => ['required_without:message', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'message_type'  => ['required', 'in:text,image,file'],
            'group_id'      => ['required_if:type,group', 'nullable', 'integer'],
            'receiver_type' => ['required_if:type,dm', 'nullable', 'in:user,sales'],
            'receiver_id'   => ['required_if:type,dm', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'          => 'Tipe pesan wajib diisi',
            'type.in'                => 'Tipe pesan tidak valid',
            'message.required_without' => 'Pesan atau file wajib diisi',
            'file.mimes'             => 'Format file harus jpg, jpeg, png, atau pdf',
            'file.max'               => 'Ukuran file maksimal 5MB',
            'group_id.required_if'   => 'Group ID wajib diisi untuk pesan grup',
            'receiver_id.required_if' => 'Penerima wajib diisi untuk pesan langsung',
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
