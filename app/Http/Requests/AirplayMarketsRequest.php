<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class AirplayMarketsRequest extends LabelFormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('airplay_markets'))) {
            $this->merge(['airplay_markets' => array_values(array_unique(array_filter(
                preg_split('/[\s,;]+/', strtoupper($this->input('airplay_markets'))) ?: [],
            )))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'airplay_markets' => ['required', 'array', 'min:1', 'max:10'],
            'airplay_markets.*' => ['string', 'size:2', 'alpha:ascii'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'airplay_markets.*' => '":input" is not a two-letter country code such as DE.',
        ];
    }
}
