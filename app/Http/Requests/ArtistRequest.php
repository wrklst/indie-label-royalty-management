<?php

namespace App\Http\Requests;

use App\Models\Artist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ArtistRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->nullify(['alias_of_id']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $artist = $this->route('artist');

        return [
            'name' => ['required', 'string', 'max:255'],
            'alias_of_id' => [
                'nullable', 'integer', $this->inLabel('artists')->whereNull('alias_of_id'),
                Rule::notIn([$artist instanceof Artist ? $artist->id : null]),
                Rule::prohibitedIf(fn (): bool => $artist instanceof Artist && $artist->aliases()->exists()),
            ],
            'spotify_id' => ['nullable', 'string', 'max:255'],
            'discogs_search_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alias_of_id.exists' => 'Choose an artist that is not itself an alias.',
            'alias_of_id.prohibited' => 'This artist has aliases of its own and cannot become an alias.',
        ];
    }
}
