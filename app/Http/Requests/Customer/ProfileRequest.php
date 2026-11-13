<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** A signed-in user (customer or admin) editing their own profile. */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{6,30}$/'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', Rule::in(config('countries'))],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            // Changing the password needs the current one.
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }
}
