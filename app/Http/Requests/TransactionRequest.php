<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TransactionRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->nullify(['conversion_rate', 'quantity', 'price', 'release_id', 'track_id', 'contract_id', 'payee_id', 'ledger_id', 'region']);

        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
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
            'type' => ['required', Rule::enum(TransactionType::class)],
            'timestamp' => ['required', 'date'],
            'paid_timestamp' => ['required', 'date'],
            'total' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'conversion_rate' => ['nullable', 'numeric', 'gt:0'],
            'quantity' => ['nullable', 'numeric'],
            'price' => ['nullable', 'numeric'],
            'release_id' => ['nullable', 'integer', $this->inLabel('releases')],
            'track_id' => ['nullable', 'integer', $this->inLabel('tracks')],
            'contract_id' => ['nullable', 'integer', $this->inLabel('contracts')],
            'payee_id' => ['nullable', 'required_if:type,transfer', 'integer', $this->inLabel('payees')],
            'ledger_id' => ['nullable', 'integer', $this->inLabel('ledgers')],
            'category' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'size:2', 'alpha'],
            'retailer' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'published' => ['boolean'],
            'billable' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['payee_id.required_if' => 'A transfer needs a payee.'];
    }
}
