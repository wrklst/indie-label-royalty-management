<?php

namespace App\Http\Requests;

use App\Models\Period;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class PeriodRequest extends LabelFormRequest
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
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * A period that was paid out keeps its dates: moving them would move
     * income in or out of balances that were settled already.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $period = $this->route('period');

                if (! $period instanceof Period || $validator->errors()->isNotEmpty() || ! $period->payouts()->exists()) {
                    return;
                }

                if (! $period->starts_on->isSameDay($this->date('starts_on')) || ! $period->ends_on->isSameDay($this->date('ends_on'))) {
                    $validator->errors()->add('starts_on', 'This period has payouts, so its dates can no longer change.');
                }
            },
        ];
    }
}
