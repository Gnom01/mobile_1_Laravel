<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmDashboardBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:60'],
            'subtitle' => [$required, 'string', 'max:80'],
            'description' => [$required, 'string', 'max:200'],
            'color_start' => [$required, 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_end' => [$required, 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'action_type' => [
                'nullable',
                Rule::in(['offers', 'payments', 'schedule', 'notifications', 'url']),
            ],
            'action_url' => ['nullable', 'url', 'required_if:action_type,url'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
