<?php

namespace App\Http\Requests;

use App\Services\Releases\ZebralutionSheet;
use App\Support\Services;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * What one service card on Connections sends: the switch and the options
 * of that service, and nothing of another one.
 */
class ServiceSettingsRequest extends LabelFormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('origin_country'))) {
            $this->merge(['origin_country' => strtoupper(trim($this->input('origin_country')))]);
        }

        $this->nullify(['number', 'origin_country', 'price_group', 'work_rights']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            ...match ($this->route('service')) {
                Services::ZEBRALUTION => [
                    'number' => ['nullable', 'string', 'max:32'],
                    'origin_country' => ['nullable', 'string', 'size:2', 'alpha:ascii'],
                    'price_group' => ['nullable', 'integer', Rule::in(ZebralutionSheet::PRICE_GROUPS)],
                    'work_rights' => ['nullable', Rule::in(ZebralutionSheet::WORK_RIGHTS)],
                ],
                Services::BANDCAMP => [
                    'disable_fee' => ['boolean'],
                    'exclude_physical' => ['boolean'],
                ],
                default => [],
            },
        ];
    }
}
