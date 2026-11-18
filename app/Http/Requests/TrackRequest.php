<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class TrackRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->nullify(['artist_id', 'contract_id', 'track_number', 'duration', 'mechanicals_rate', 'ppd_rate']);
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
            'artist_id' => ['nullable', 'integer', $this->inLabel('artists')],
            'contract_id' => ['nullable', 'integer', $this->inLabel('contracts')],
            'isrc' => ['nullable', 'string', 'max:16'],
            'iswc' => ['nullable', 'string', 'max:16'],
            'track_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'mechanicals_rate' => ['nullable', 'numeric', 'min:0'],
            'ppd_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
