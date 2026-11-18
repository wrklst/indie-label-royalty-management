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
            'bandcamp_disable_fee' => ['boolean'],
            'bandcamp_exclude_physical' => ['boolean'],
        ];
    }
}
