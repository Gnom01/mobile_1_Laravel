<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderDashboardBannersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:dashboard_banners,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
