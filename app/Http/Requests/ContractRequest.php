<?php

namespace App\Http\Requests;

use App\Models\Contract;
use App\Support\Decimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContractRequest extends LabelFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $contract = $this->route('contract');

        return [
            'name' => ['required', 'string', 'max:255'],
            'memo' => ['nullable', 'string', 'max:5000'],
            'splits' => ['array'],
            'splits.*.payee_id' => ['required', 'integer', $this->inLabel('payees')],
            'splits.*.ledger_id' => ['nullable', 'integer', $this->inLabel('ledgers')],
            'splits.*.territory_id' => ['nullable', 'integer', $this->inLabel('territories')],
            'splits.*.percentage' => ['required', 'numeric', 'decimal:0,7', 'gt:0', 'max:100'],
            'children' => ['array'],
            'children.*.contract_id' => ['required', 'integer', 'distinct', $this->inLabel('contracts'), Rule::notIn([$contract instanceof Contract ? $contract->id : null])],
            'children.*.weight' => ['required', 'numeric', 'decimal:0,9', 'gt:0', 'max:999999999'],
        ];
    }

    /**
     * Rules that look at the contract as a whole.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->ensureSplitsFitTheWhole($validator);
                $this->ensureNoContractCycle($validator);
            },
        ];
    }

    /**
     * Worldwide splits, plus those of any one territory, may not pay out
     * more than the income they are taken from.
     */
    private function ensureSplitsFitTheWhole(Validator $validator): void
    {
        $world = '0';
        $territories = [];

        foreach ($this->input('splits', []) as $split) {
            $territory = $split['territory_id'] ?? null;
            $percentage = Decimal::parse($split['percentage'] ?? null);

            if ($territory === null || $territory === '') {
                $world = Decimal::add($world, $percentage);
            } else {
                $territories[$territory] = Decimal::add($territories[$territory] ?? '0', $percentage);
            }
        }

        $largest = array_reduce($territories, Decimal::max(...), '0');

        if (Decimal::cmp(Decimal::add($world, $largest), '100') === 1) {
            $validator->errors()->add('splits', 'The splits add up to more than 100%. A contract cannot pay out more than it earns.');
        }
    }

    /**
     * A contract may not be prorated across a contract that is, directly
     * or further down, prorated across it.
     */
    private function ensureNoContractCycle(Validator $validator): void
    {
        $contract = $this->route('contract');

        if (! $contract instanceof Contract) {
            return;
        }

        $links = DB::table('contract_links')->get(['parent_contract_id', 'child_contract_id'])->groupBy('parent_contract_id');
        $pending = array_map(intval(...), array_column($this->input('children', []), 'contract_id'));
        $seen = [];

        while ($pending !== []) {
            $id = array_pop($pending);

            if ($id === $contract->id) {
                $validator->errors()->add('children', 'One of these contracts is itself prorated across this contract, which would go round in a circle.');

                return;
            }

            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;

            foreach ($links[$id] ?? [] as $link) {
                $pending[] = (int) $link->child_contract_id;
            }
        }
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
