<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $isAdjustment = $this->input('operation') === 'adjustment';

        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'customer')],
            'operation' => ['required', Rule::in(['recharge', 'adjustment'])],
            // Recharge is always positive; an adjustment may be negative to take credit back.
            'amount' => $isAdjustment
                ? ['required', 'numeric', 'between:-1000000,1000000', 'not_in:0', 'decimal:0,2']
                : ['required', 'numeric', 'gt:0', 'max:1000000', 'decimal:0,2'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => [$isAdjustment ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['user_id' => 'customer', 'note' => $this->input('operation') === 'adjustment' ? 'reason' : 'note'];
    }
}
