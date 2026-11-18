<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class PayoutRequest extends LabelFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payee_id' => ['required', 'integer', $this->inLabel('payees')],
            'total' => ['required', 'numeric', 'gt:0'],
            'paid_on' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
