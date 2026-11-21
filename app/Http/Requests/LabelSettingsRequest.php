<?php

namespace App\Http\Requests;

use App\Services\Releases\ZebralutionSheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

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

        if (is_string($this->input('zebralution_origin_country'))) {
            $this->merge(['zebralution_origin_country' => strtoupper(trim($this->input('zebralution_origin_country')))]);
        }

        $this->nullify(['zebralution_number', 'zebralution_origin_country', 'zebralution_price_group', 'zebralution_work_rights']);

        if (is_string($this->input('airplay_markets'))) {
            $this->merge(['airplay_markets' => array_values(array_unique(array_filter(
                preg_split('/[\s,;]+/', strtoupper($this->input('airplay_markets'))) ?: [],
            )))]);
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
            'mechanicals_usd_rate' => ['nullable', 'numeric', 'decimal:0,10', 'gt:0', 'max:999999'],
            'mechanicals_include_physical' => ['boolean'],
            'ignore_negative_quantities' => ['boolean'],
            'airplay_markets' => ['array', 'min:1', 'max:10'],
            'airplay_markets.*' => ['string', 'size:2', 'alpha:ascii'],
            'zebralution_number' => ['nullable', 'string', 'max:32'],
            'zebralution_origin_country' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
            'zebralution_price_group' => ['nullable', 'integer', Rule::in(ZebralutionSheet::PRICE_GROUPS)],
            'zebralution_work_rights' => ['nullable', Rule::in(ZebralutionSheet::WORK_RIGHTS)],
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
