<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class AirplayRequest extends LabelFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->nullify(['track_id', 'track_name', 'artist_name', 'station', 'media_type', 'region', 'period_start', 'period_end', 'notes']);

        if (is_string($this->input('region'))) {
            $this->merge(['region' => strtoupper($this->input('region'))]);
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
            'track_id' => ['nullable', 'integer', $this->inLabel('tracks')],
            'track_name' => ['nullable', 'required_without:track_id', 'string', 'max:255'],
            'artist_name' => ['nullable', 'string', 'max:255'],
            'station' => ['nullable', 'string', 'max:255'],
            'media_type' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'alpha', 'size:2'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
