<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            // HTML from the editor; cleaned in ServiceManagementService before it is stored.
            'description' => ['nullable', 'string', 'max:30000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'delivery_time' => ['nullable', 'string', 'max:60'],
            'is_instant' => ['nullable', 'boolean'],
            'field_ids' => ['nullable', 'array'],
            'field_ids.*' => ['integer'],
            // Optional link to a connected provider: both or neither.
            'api_provider_id' => ['nullable', 'integer', 'exists:api_providers,id', 'required_with:remote_service_id'],
            'remote_service_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/', 'required_with:api_provider_id'],
        ];
    }

    public function attributes(): array
    {
        return ['service_category_id' => 'category', 'api_provider_id' => 'provider', 'remote_service_id' => 'provider service ID'];
    }
}
