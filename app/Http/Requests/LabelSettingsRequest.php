<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class LabelSettingsRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'accounts_mechanicals' => ['boolean'],
            'accounts_ppd' => ['boolean'],
            'accounts_reserves' => ['boolean'],
            'mechanicals_usd_rate' => ['nullable', 'numeric', 'decimal:0,10', 'gt:0', 'max:999999'],
            'mechanicals_include_physical' => ['boolean'],
            'ignore_negative_quantities' => ['boolean'],
        ];
    }
}
