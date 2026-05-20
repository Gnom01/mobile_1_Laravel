<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrmPushTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'         => ['required_without:device_token_id', 'nullable', 'integer'],
            'device_token_id' => ['required_without:user_id', 'nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category' => ['nullable', 'in:marketing,group,instructor,organization,payment,reminder,schedule_change,cancelled_class,system'],
            'deep_link' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string'],
        ];
    }
}