<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\LabelFormRequest;
use App\Models\BandcampConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class BandcampSettingsRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->nullify(['member_band_id']);
    }

    /**
     * Only accounts Bandcamp listed for the stored credentials can be chosen.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bands = collect(BandcampConnection::query()->first()->bands ?? [])->keyBy('band_id');
        $members = collect($bands->get((int) $this->input('band_id'))['member_bands'] ?? [])->pluck('band_id');

        return [
            'band_id' => ['required', 'integer', Rule::in($bands->keys()->all())],
            'member_band_id' => ['nullable', 'integer', Rule::in($members->all())],
            'sync_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:2008-01-01', 'before_or_equal:today'],
            'enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'band_id.in' => 'Choose one of the accounts Bandcamp listed for your credentials.',
            'member_band_id.in' => 'Choose an artist that belongs to the selected account.',
        ];
    }
}
