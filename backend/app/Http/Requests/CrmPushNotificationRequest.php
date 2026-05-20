<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrmPushNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_id' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category' => ['required', 'in:marketing,group,instructor,organization,payment,reminder,schedule_change,cancelled_class,system'],
            'type' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'in:normal,high'],
            'image_url' => ['nullable', 'string'],
            'deep_link' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'filters' => ['nullable', 'array'],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['integer'],
            'scheduled_at' => ['nullable', 'date'],
            'created_by_crm_user_id' => ['nullable', 'integer'],
            'segment_name' => ['nullable', 'string', 'max:255'],
            'save_segment' => ['nullable', 'boolean'],
        ];
    }
}
