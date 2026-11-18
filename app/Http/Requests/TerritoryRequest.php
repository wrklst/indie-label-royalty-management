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
        if (is_string($this->input('countries'))) {
            $this->merge(['countries' => array_values(array_unique(array_filter(array_map(
                fn (string $code): string => strtoupper(trim($code)),
                preg_split('/[\s,;]+/', $this->input('countries')) ?: [],
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
            'countries.*' => ['string', 'size:2', 'alpha'],
        ];
    }
}
