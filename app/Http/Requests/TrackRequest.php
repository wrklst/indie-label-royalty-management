<?php

namespace App\Http\Requests;

use App\Enums\ArtistRole;
use App\Rules\Isrc;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TrackRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // A form with no credit rows sends an empty marker instead of a list.
        if ($this->input('artists') === '' || $this->input('artists') === null && $this->has('artists')) {
            $this->merge(['artists' => []]);
        }

        $this->nullify(['artist_id', 'contract_id', 'track_number', 'duration', 'accounts_mechanicals', 'mechanicals_rate', 'ppd_rate', 'disc_number', 'explicit', 'bpm', 'preview_start']);

        // Credits may arrive as "Name One, Name Two" per role from text fields.
        $credits = $this->input('credits');

        if (is_array($credits)) {
            $this->merge(['credits' => array_filter(array_map(function (mixed $names): mixed {
                if ($names === null) {
                    return [];
                }

                if (is_string($names)) {
                    $names = preg_split('/\s*[,;\n]\s*/', $names) ?: [];
                }

                return is_array($names) ? array_values(array_filter(array_map(fn (mixed $name): mixed => is_string($name) ? trim($name) : $name, $names), fn (mixed $name): bool => $name !== '')) : $names;
            }, $credits), fn (mixed $names): bool => $names !== [])]);
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
            'artist_id' => ['nullable', 'integer', $this->inLabel('artists')],
            'contract_id' => ['nullable', 'integer', $this->inLabel('contracts')],
            'isrc' => ['nullable', 'string', 'max:16', new Isrc],
            'iswc' => ['nullable', 'string', 'max:16'],
            'track_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'duration' => ['nullable', 'integer', 'min:0'],
            // Null follows the release.
            'accounts_mechanicals' => ['nullable', 'boolean'],
            'mechanicals_rate' => ['nullable', 'numeric', 'min:0'],
            'ppd_rate' => ['nullable', 'numeric', 'min:0'],
            'disc_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'version' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:8'],
            'explicit' => ['nullable', Rule::in(['explicit', 'clean'])],
            'bpm' => ['nullable', 'numeric', 'decimal:0,2', 'min:20', 'max:400'],
            'musical_key' => ['nullable', 'string', 'max:20'],
            'preview_start' => ['nullable', 'integer', 'min:0', 'max:36000'],
            'p_line' => ['nullable', 'string', 'max:255'],
            'lyrics' => ['nullable', 'string', 'max:50000'],
            'credits' => ['nullable', 'array:composers,lyricists,producers,remixers,publishers'],
            'credits.*' => ['array'],
            'credits.*.*' => ['string', 'max:255'],
            'artists' => ['array'],
            'artists.*.artist_id' => ['required', 'integer', $this->inLabel('artists')],
            'artists.*.role' => ['required', Rule::enum(ArtistRole::class)],
        ];
    }
}
