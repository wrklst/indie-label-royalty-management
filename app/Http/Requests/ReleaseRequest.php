<?php

namespace App\Http\Requests;

use App\Models\Release;
use App\Rules\Isrc;
use App\Rules\Upc;
use Illuminate\Contracts\Validation\ValidationRule;

class ReleaseRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->nullify(['artist_id', 'contract_id', 'released_on', 'mechanicals_rate', 'ppd_rate', 'reserves_rate']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $release = $this->route('release');

        return [
            'name' => ['required', 'string', 'max:255'],
            'artist_id' => ['nullable', 'integer', $this->inLabel('artists')],
            'contract_id' => ['nullable', 'integer', $this->inLabel('contracts')],
            'catalog_number' => ['nullable', 'string', 'max:255'],
            'upc' => ['nullable', 'string', 'max:32', new Upc],
            'released_on' => ['nullable', 'date'],
            'spotify_id' => ['nullable', 'string', 'max:255'],
            'discogs_id' => ['nullable', 'string', 'max:255'],
            'accounts_mechanicals' => ['boolean'],
            'mechanicals_rate' => ['nullable', 'numeric', 'min:0'],
            'ppd_rate' => ['nullable', 'numeric', 'min:0'],
            'reserves_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tracks' => ['array'],
            'tracks.*.id' => ['nullable', 'integer', 'distinct', $this->inLabel('tracks')->where('release_id', $release instanceof Release ? $release->id : 0)],
            'tracks.*.name' => ['required', 'string', 'max:255'],
            'tracks.*.artist_id' => ['nullable', 'integer', $this->inLabel('artists')],
            'tracks.*.contract_id' => ['nullable', 'integer', $this->inLabel('contracts')],
            'tracks.*.isrc' => ['nullable', 'string', 'max:16', new Isrc],
            'tracks.*.iswc' => ['nullable', 'string', 'max:16'],
            'tracks.*.track_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'tracks.*.duration' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tracks.*.name' => 'track name', 'tracks.*.isrc' => 'ISRC'];
    }
}
