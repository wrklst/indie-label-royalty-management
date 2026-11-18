<?php

namespace App\Services\Royalties\Tools;

use App\Models\Label;
use App\Support\Decimal;

/**
 * How a label wants its mechanicals and PPD tools to count.
 */
final readonly class ToolSettings
{
    /**
     * @param  numeric-string|null  $usdRate  What one US dollar is worth in the label currency, for statutory mechanical rates.
     */
    public function __construct(
        public ?string $usdRate,
        public bool $includePhysical,
        public bool $ignoreNegativeQuantities,
    ) {}

    public static function for(Label $label): self
    {
        $settings = $label->royalty_tool_settings ?? [];
        $usdRate = Decimal::parse($settings['usd_rate'] ?? null);

        return new self(
            $usdRate !== null && Decimal::isPositive($usdRate) ? $usdRate : null,
            (bool) ($settings['include_physical'] ?? false),
            (bool) ($settings['ignore_negative_quantities'] ?? false),
        );
    }
}
