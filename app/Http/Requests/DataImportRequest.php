<?php

namespace App\Http\Requests;

use App\Services\Imports\ImporterRegistry;
use App\Support\IncomingFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class DataImportRequest extends LabelFormRequest
{
    public const int MAX_KILOBYTES = 51200;

    /** @var list<string> */
    public const array EXTENSIONS = ['csv', 'txt', 'tsv', 'xlsx'];

    /**
     * The statement file, posted here or already in the bucket.
     */
    public function statement(int $labelId): IncomingFile
    {
        return IncomingFile::from($this, $labelId, self::MAX_KILOBYTES, extensions: self::EXTENSIONS, messages: [
            'type' => __('A statement has to be a CSV or Excel (.xlsx) file.'),
        ]);
    }

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
            'file' => ['required_without:upload', 'file', 'max:'.self::MAX_KILOBYTES, 'extensions:'.implode(',', self::EXTENSIONS)],
            ...IncomingFile::RULES,
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
