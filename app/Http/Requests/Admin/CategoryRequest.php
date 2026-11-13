<?php

namespace App\Http\Requests\Admin;

use App\Models\CategoryField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'fields' => ['nullable', 'array', 'max:30'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.input_type' => ['required', Rule::in(array_keys(CategoryField::TYPES))],
            'fields.*.placeholder' => ['nullable', 'string', 'max:150'],
            'fields.*.is_required' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['fields.*.label' => 'field label', 'fields.*.input_type' => 'input type'];
    }
}
