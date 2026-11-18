<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class ItemRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->nullify(['release_id', 'track_id', 'contract_id', 'territory_id']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'release_id' => ['nullable', 'integer', $this->inLabel('releases')],
            'track_id' => ['nullable', 'integer', $this->inLabel('tracks')],
            'contract_id' => ['nullable', 'integer', $this->inLabel('contracts')],
            'territory_id' => ['nullable', 'integer', $this->inLabel('territories')],
        ];
    }
}
