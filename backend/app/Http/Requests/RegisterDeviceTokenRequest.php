<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'in:ios,android'],
            'token' => ['required', 'string'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:20'],
        ];
    }
}
