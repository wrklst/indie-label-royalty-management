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
            // An expense is typed as a positive amount and stored negative; a refund or credit is stored positive.
            'total' => ['required', 'numeric', 'decimal:0,15', 'between:-999999999999,999999999999', ...($this->input('type') === TransactionType::Expense->value ? ['min:0'] : [])],
            'refund' => ['boolean'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'conversion_rate' => ['nullable', 'numeric', 'decimal:0,10', 'min:0.0000000001', 'max:9999999999'],
            'quantity' => ['nullable', 'numeric', 'decimal:0,8', 'between:-999999999999,999999999999'],
            'price' => ['nullable', 'numeric', 'decimal:0,15', 'between:-999999999999,999999999999'],
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
            'recoupable' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payee_id.required_if' => 'A transfer needs a payee.',
            'total.min' => 'Enter an expense as a positive amount. For a refund or credit, tick “Refund or credit”.',
        ];
    }
}
