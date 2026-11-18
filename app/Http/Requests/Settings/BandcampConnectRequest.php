<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\LabelFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class BandcampConnectRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(['client_id', 'client_secret'])
            ->filter(fn (string $key): bool => is_string($this->input($key)))
            ->mapWithKeys(fn (string $key): array => [$key => trim($this->input($key))])
            ->all());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:1000'],
        ];
    }
}
