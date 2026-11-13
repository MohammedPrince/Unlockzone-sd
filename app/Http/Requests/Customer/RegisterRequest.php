<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** The four registration steps: account, billing information, contact info, confirm. */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Step 1: account
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            // Step 2: billing information
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'address1' => ['required', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'country' => ['required', Rule::in(config('countries'))],
            'state' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            // Step 3: contact info
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{6,30}$/'],
            // Step 4: confirm
            'terms' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return ['address1' => 'address 1', 'address2' => 'address 2', 'zip_code' => 'zip code / post code', 'phone' => 'mobile'];
    }
}
