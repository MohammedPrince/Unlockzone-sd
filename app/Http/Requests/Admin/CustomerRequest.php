<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Admin creating or editing a customer. On edit the password is optional (blank keeps the current one). */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{6,30}$/'],
            'password' => [$id ? 'nullable' : 'required', Password::min(8)],
            'status' => [$id ? 'nullable' : 'required', Rule::in(User::STATUSES)],
            // Free label such as VIP or Reseller; empty means a normal customer.
            'customer_tag' => ['nullable', 'string', 'max:30', 'regex:/^[\pL\pN _\-+]+$/u'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', Rule::in(config('countries'))],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
