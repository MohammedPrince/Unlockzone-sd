<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape check only. The per-field rules (IMEI = 15 digits, etc.) depend on the service
 * and are applied in ServiceRequestService. No price is accepted from the browser.
 */
class SubmitServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'fields' => ['nullable', 'array', 'max:30'],
            'fields.*' => ['nullable', 'string', 'max:255'],
            // The "Agree with terms of services" tick box, when Settings asks for it.
            'terms' => setting('require_terms') === '1' ? ['accepted'] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return ['terms.accepted' => __('Please agree with the terms of services before placing the order.')];
    }
}
