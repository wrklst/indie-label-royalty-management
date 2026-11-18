<?php

namespace App\Http\Requests;

use App\Models\Label;
use App\Support\CurrentLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Base for requests that write records of the current label.
 */
abstract class LabelFormRequest extends FormRequest
{
    public function label(): Label
    {
        return app(CurrentLabel::class)->get();
    }

    /**
     * An exists rule that only accepts records of the current label.
     */
    protected function inLabel(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('label_id', $this->label()->id);
    }

    /**
     * Turn empty strings from select boxes and inputs into null.
     *
     * @param  list<string>  $keys
     */
    protected function nullify(array $keys): void
    {
        $this->merge(collect($keys)
            ->filter(fn (string $key): bool => $this->has($key) && $this->input($key) === '')
            ->mapWithKeys(fn (string $key): array => [$key => null])
            ->all());
    }
}
