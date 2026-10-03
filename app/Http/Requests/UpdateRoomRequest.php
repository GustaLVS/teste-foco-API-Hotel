<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('PUT')
            ? ['required']
            : ['sometimes', 'required'];

        return [
            'hotel_id' => array_merge($presence, [
                'integer',
                'min:1',
                'exists:hotels,id',
            ]),
            'name' => array_merge($presence, [
                'string',
                'max:255',
            ]),
        ];
    }
}