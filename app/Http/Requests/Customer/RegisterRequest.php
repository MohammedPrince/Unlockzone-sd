<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Sign-up on one page: username, email, mobile, password and the terms. No name or address is asked for;
 * the first name starts as the username (see CustomerService::register) and can be changed in My profile.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{6,30}$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return ['phone' => 'mobile'];
    }
}
