<?php

namespace App\Services\Imports\Importers;

use App\Enums\AirplayOrigin;
use App\Models\Airplay;
use App\Models\DataImport;
use App\Models\Transaction;
use App\Services\Imports\Identifiers;
use App\Services\Imports\ImportRow;
use App\Services\Imports\Row;
use App\Support\Decimal;
use Carbon\CarbonImmutable;

/**
 * The "recording allocation" sheet of a Rightz Audio statement: one line
 * per recording, society and usage period. Every line is kept as an
 * airplay; lines from GVL also name the broadcaster.
 */
class RightzAudioImporter extends BaseImporter
{
    public const string KEY = 'rightz-audio';

    public function key(): string
    {
        return self::KEY;
    }

    public function sourceName(): string
    {
        return 'Rightz Audio';
    }

    public function description(): string
    {
        return 'Neighbouring rights statements from Rightz Audio, as downloaded (Excel). Every line is listed under Airplay, with the station when the society names it.';
    }

    public function requiresPaidDate(): bool
    {
        return true;
    }

    public function requiredColumns(): array
    {
        return ['CMO', 'Source Attributes', 'Total Client [€]'];
    }

    protected function map(Row $row): ImportRow
    {
        $attributes = self::attributes($row->string('Source Attributes'));
        $society = $row->string('CMO');
        $version = $row->string('Recording Display Title Version');
        $allocation = self::period($row->string('Allocation Period'));

        return new ImportRow(
            total: $row->requireDecimal('Total Client [€]'),
            timestamp: $allocation['end'] ?? null,
            currency: 'EUR',
            category: 'Rights',
            format: 'Neighboring Rights',
            usageType: $attributes['media_type'] ?? $attributes['category_of_service'] ?? null,
            region: $row->region('Country Code'),
            provider: $society,
            retailer: $attributes['broadcaster'] ?? $society,
            source: 'Rightz Audio',
            memo: $row->string('Statement Name'),
            artist: $row->string('Recording Display Artist'),
            trackName: $row->requireString('Recording Display Title').($version === null ? '' : " ({$version})"),
            isrc: $row->isrc('ISRC'),
            raw: $row->raw,
        );
    }

    /**
     * Keep every line of an import as an airplay: with the broadcaster
     * when the society names it, as an unidentified play otherwise. A play
     * that is already listed gets the new amount added, so later
     * correction runs do not list it twice.
     */
    public static function recordAirplays(DataImport $import): void
    {
        $transactions = Transaction::query()->withoutGlobalScope('label')
            ->where('data_import_id', $import->id)
            ->get(['id', 'track_id', 'region', 'provider', 'usage_type', 'total', 'converted_total', 'raw_data']);

        foreach ($transactions as $transaction) {
            $raw = $transaction->raw_data ?? [];
            $station = trim((string) (self::attributes($raw['Source Attributes'] ?? null)['broadcaster'] ?? ''));
            $usage = self::period($raw['Usage Period'] ?? null);
            $version = trim((string) ($raw['Recording Display Title Version'] ?? ''));

            $play = [
                'label_id' => $import->label_id,
                'isrc' => Identifiers::isrc($raw['ISRC'] ?? null),
                'track_name' => trim((string) ($raw['Recording Display Title'] ?? '')).($version === '' ? '' : " ({$version})"),
                'society' => $transaction->provider,
                'station' => $station === '' ? null : $station,
            ];

            // A society that names no broadcaster pays one play in many small lines; they are one unidentified play.
            $airplay = Airplay::query()->withoutGlobalScope('label')->firstOrNew($station === '' ? $play : [
                ...$play, 'period_start' => $usage['start'] ?? null, 'period_end' => $usage['end'] ?? null,
            ]);

            $airplay->fill([
                'track_id' => $transaction->track_id ?? $airplay->track_id,
                'data_import_id' => $import->id,
                'origin' => $airplay->origin ?? AirplayOrigin::Statement,
                'artist_name' => $raw['Recording Display Artist'] ?? null,
                'media_type' => $transaction->usage_type,
                'region' => $transaction->region,
                'period_start' => collect([$airplay->period_start?->toDateString(), $usage['start'] ?? null])->filter()->min(),
                'period_end' => collect([$airplay->period_end?->toDateString(), $usage['end'] ?? null])->filter()->max(),
                'amount' => Decimal::add($airplay->amount, $transaction->converted_total ?? $transaction->total),
            ])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributes(mixed $json): array
    {
        $attributes = is_string($json) ? json_decode($json, true) : null;

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * "[2025-01-01,2026-01-01)" is the whole of 2025: the end is not part of it.
     *
     * @return array{start: string, end: string}|array{}
     */
    private static function period(mixed $range): array
    {
        if (! is_string($range) || preg_match('/^\[(\d{4}-\d{2}-\d{2}),\s*(\d{4}-\d{2}-\d{2})\)$/', trim($range), $dates) !== 1) {
            return [];
        }

        return ['start' => $dates[1], 'end' => CarbonImmutable::parse($dates[2])->subDay()->toDateString()];
    }
}
