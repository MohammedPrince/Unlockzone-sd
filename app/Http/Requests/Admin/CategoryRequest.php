<?php

namespace App\Http\Requests\Admin;

use App\Models\CategoryField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /** Input fields one category can have. */
    public const MAX_FIELDS = 150;

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
            // Each field row sends 6 values; 150 rows stay under PHP's usual max_input_vars of 1000.
            'fields' => ['nullable', 'array', 'max:' . self::MAX_FIELDS],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required', 'string', 'max:100'],
            'fields.*.input_type' => ['required', Rule::in(array_keys(CategoryField::TYPES))],
            'fields.*.placeholder' => ['nullable', 'string', 'max:150'],
            'fields.*.is_required' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The form says how many field rows it sent (fields_total, the first value in the form). If the server
     * received fewer, PHP cut the form short (max_input_vars): saving would delete the missing fields from
     * the category and its services, so it is refused instead.
     */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator) {
            $sent = $this->input('fields_total');
            if ($sent !== null && is_numeric($sent) && (int) $sent !== count((array) $this->input('fields', []))) {
                $validator->errors()->add('fields', __('The server received only part of the form, so nothing was saved (no field was removed). Ask the hosting to raise max_input_vars, or use fewer fields.'));
            }
        }];
    }

    public function attributes(): array
    {
        return ['fields.*.label' => 'field label', 'fields.*.input_type' => 'input type'];
    }
}
