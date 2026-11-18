<?php

namespace App\Enums;

use App\Models\Label;

/**
 * The manually run tools that turn rates stored in the catalog into
 * ordinary transactions. Each is switched on per label.
 */
enum RoyaltyTool: string
{
    case Mechanicals = 'mechanicals';
    case Ppd = 'ppd';
    case Reserves = 'reserves';

    public function label(): string
    {
        return match ($this) {
            self::Mechanicals => 'Mechanicals',
            self::Ppd => 'PPD',
            self::Reserves => 'Reserves',
        };
    }

    /**
     * The importer key of the data imports a run of the tool creates.
     */
    public function importer(): string
    {
        return "{$this->value}-tool";
    }

    /**
     * The display name of an importer key when it belongs to a tool.
     */
    public static function importerName(string $importer): ?string
    {
        foreach (self::cases() as $tool) {
            if ($tool->importer() === $importer) {
                return "{$tool->label()} tool";
            }
        }

        return null;
    }

    public function enabledFor(Label $label): bool
    {
        return match ($this) {
            self::Mechanicals => $label->accounts_mechanicals,
            self::Ppd => $label->accounts_ppd,
            self::Reserves => $label->accounts_reserves,
        };
    }

    /**
     * @return list<string>
     */
    public static function importers(): array
    {
        return array_map(fn (self $tool): string => $tool->importer(), self::cases());
    }
}
