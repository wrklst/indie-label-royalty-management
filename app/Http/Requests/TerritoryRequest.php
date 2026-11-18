<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class TerritoryRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $countries = $this->input('countries');

        if (is_string($countries)) {
            $countries = preg_split('/[\s,;]+/', $countries) ?: [];
        }

        if (is_array($countries)) {
            $this->merge(['countries' => array_values(array_unique(array_filter(array_map(
                fn (mixed $code): mixed => is_string($code) ? strtoupper(trim($code)) : $code,
                $countries,
            ))))]);
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
            'countries' => ['required', 'array', 'min:1'],
            'countries.*' => ['string', 'size:2', 'alpha:ascii'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'countries.*' => '":input" is not a two-letter country code such as DE.',
        ];
    }
}
