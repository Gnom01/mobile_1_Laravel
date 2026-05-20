<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrmPushPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filters' => ['nullable', 'array'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
