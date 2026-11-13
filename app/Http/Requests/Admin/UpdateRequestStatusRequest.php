<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['processing', 'completed', 'failed', 'cancelled'])],
            // The result is what the customer sees (unlock code, confirmation, etc.).
            'result' => ['nullable', 'required_if:status,completed', 'string', 'max:5000'],
            'error' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['error' => 'reason'];
    }
}
