<?php

namespace App\Models\Concerns;

use App\Models\Label;
use App\Support\CurrentLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current label and fills label_id on creation.
 */
trait BelongsToLabel
{
    public static function bootBelongsToLabel(): void
    {
        static::addGlobalScope('label', function (Builder $builder): void {
            $labelId = app(CurrentLabel::class)->id();

            if ($labelId !== null) {
                $builder->where($builder->qualifyColumn('label_id'), $labelId);
            }
        });

        static::creating(function ($model): void {
            $model->label_id ??= app(CurrentLabel::class)->id();
        });
    }

    /**
     * @return BelongsTo<Label, $this>
     */
    public function label(): BelongsTo
    {
        return $this->belongsTo(Label::class);
    }
}
