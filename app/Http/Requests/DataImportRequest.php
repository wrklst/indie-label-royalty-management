<?php

namespace App\Http\Requests;

use App\Services\Imports\ImporterRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class DataImportRequest extends LabelFormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->nullify(['default_timestamp', 'default_paid_timestamp', 'conversion_rate', 'name']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'importer' => ['required', Rule::in(app(ImporterRegistry::class)->choices())],
            'file' => ['required', 'file', 'max:51200', 'extensions:csv,txt,tsv,xlsx'],
            'name' => ['nullable', 'string', 'max:255'],
            'default_timestamp' => ['nullable', 'date'],
            'replace_timestamp' => ['boolean'],
            'default_paid_timestamp' => ['nullable', 'date'],
            'replace_paid_timestamp' => ['boolean'],
            'conversion_rate' => ['nullable', 'numeric', 'gt:0'],
            'skip_duplicates' => ['boolean'],
            'tags' => ['nullable', 'string', 'max:255'],
        ];
    }
}
