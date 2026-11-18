<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ContractRequest extends LabelFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'memo' => ['nullable', 'string', 'max:5000'],
            'splits' => ['array'],
            'splits.*.payee_id' => ['required', 'integer', $this->inLabel('payees')],
            'splits.*.ledger_id' => ['nullable', 'integer', $this->inLabel('ledgers')],
            'splits.*.territory_id' => ['nullable', 'integer', $this->inLabel('territories')],
            'splits.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
            'children' => ['array'],
            'children.*.contract_id' => ['required', 'integer', 'distinct', $this->inLabel('contracts'), Rule::notIn([$this->route('contract')?->id])],
            'children.*.weight' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'splits.*.payee_id' => 'payee',
            'splits.*.percentage' => 'split',
            'children.*.contract_id' => 'child contract',
            'children.*.weight' => 'weight',
        ];
    }
}
