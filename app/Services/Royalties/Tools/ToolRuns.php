<?php

namespace App\Services\Royalties\Tools;

use App\Enums\ImportStatus;
use App\Enums\RoyaltyTool;
use App\Enums\TransactionType;
use App\Models\DataImport;
use App\Models\Item;
use App\Models\Label;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Books what a tool calculated: one data import per run, holding ordinary
 * transactions on dedicated items. Deleting the import undoes the run.
 *
 * @phpstan-type Line array{
 *     release_id: int,
 *     release_name: string,
 *     track_id: int|null,
 *     track_name: string|null,
 *     role: string,
 *     suffix: string,
 *     type: TransactionType,
 *     total: numeric-string,
 *     paid_on: string,
 *     category: string,
 *     format?: string|null,
 *     memo: string
 * }
 * @phpstan-type Run array{
 *     id: int,
 *     name: string,
 *     from: string,
 *     to: string,
 *     total: string,
 *     paid_on: string|null,
 *     transactions_count: int,
 *     created_at: string|null
 * }
 */
class ToolRuns
{
    /**
     * Income the tools read. What a tool booked itself is never a sale,
     * so it is left out of every tool's basis.
     *
     * @return Builder<Transaction>
     */
    public function sales(Label $label, string $from, string $to): Builder
    {
        return Transaction::query()
            ->withoutGlobalScope('label')
            ->where('transactions.label_id', $label->id)
            ->where('transactions.type', TransactionType::Income)
            ->whereBetween('transactions.paid_timestamp', [$from, $to])
            ->where(fn (Builder $query) => $query
                ->whereNull('transactions.importer_source')
                ->orWhereNotIn('transactions.importer_source', RoyaltyTool::importers()));
    }

    /**
     * Earlier runs of the tool that are still booked, newest first.
     *
     * @return list<Run>
     */
    public function history(Label $label, RoyaltyTool $tool): array
    {
        return array_values(DataImport::query()
            ->withoutGlobalScope('label')
            ->where('label_id', $label->id)
            ->where('importer', $tool->importer())
            ->where('status', ImportStatus::Imported)
            ->latest('id')
            ->get()
            ->map(fn (DataImport $import): array => [
                'id' => $import->id,
                'name' => $import->name,
                'from' => (string) ($import->options['from'] ?? ''),
                'to' => (string) ($import->options['to'] ?? ''),
                'total' => (string) ($import->options['total'] ?? '0'),
                'paid_on' => $import->default_paid_timestamp?->toDateString(),
                'transactions_count' => $import->transactions_count,
                'created_at' => $import->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Earlier runs whose date range shares at least one day with the given
     * one. Running a tool twice over the same sales counts them twice.
     *
     * @return list<Run>
     */
    public function overlapping(Label $label, RoyaltyTool $tool, string $from, string $to): array
    {
        return array_values(array_filter(
            $this->history($label, $tool),
            fn (array $run): bool => $run['from'] !== '' && $run['from'] <= $to && $run['to'] >= $from,
        ));
    }

    /**
     * @param  array<string, mixed>  $options  What the run was calculated with, kept for the record.
     * @param  numeric-string  $total
     * @param  list<Line>  $lines
     */
    public function record(Label $label, RoyaltyTool $tool, ?User $user, string $from, string $to, string $paidOn, string $total, array $options, array $lines): DataImport
    {
        return DB::transaction(function () use ($label, $tool, $user, $from, $to, $paidOn, $total, $options, $lines): DataImport {
            $import = DataImport::query()->create([
                'label_id' => $label->id,
                'user_id' => $user?->id,
                'importer' => $tool->importer(),
                'name' => "{$tool->label()} {$from} – {$to}",
                'status' => ImportStatus::Imported,
                'default_timestamp' => $paidOn,
                'default_paid_timestamp' => $paidOn,
                'options' => [...$options, 'from' => $from, 'to' => $to, 'total' => $total],
                'rows_count' => count($lines),
                'transactions_count' => count($lines),
            ]);

            $sum = '0';

            foreach ($lines as $line) {
                Transaction::query()->create([
                    'label_id' => $label->id,
                    'data_import_id' => $import->id,
                    'release_id' => $line['release_id'],
                    'track_id' => $line['track_id'],
                    'item_id' => $this->item($label, $line)->id,
                    'type' => $line['type'],
                    'recoupable' => true,
                    'timestamp' => $line['paid_on'],
                    'paid_timestamp' => $line['paid_on'],
                    'total' => $line['total'],
                    'currency' => $label->currency,
                    'conversion_rate' => '1',
                    'converted_total' => $line['total'],
                    'category' => $line['category'],
                    'format' => $line['format'] ?? null,
                    'source' => "{$tool->label()} tool",
                    'importer_source' => $tool->importer(),
                    'memo' => $line['memo'],
                ]);

                $sum = Decimal::add($sum, $line['total']);
            }

            $import->update(['total' => $sum]);

            return $import;
        });
    }

    /**
     * The item a tool books on for a release or track, created the first
     * time it is needed. It has no contract of its own, so it follows the
     * track or release until someone sets one on the Items page.
     *
     * @param  Line  $line
     */
    private function item(Label $label, array $line): Item
    {
        $scope = ['label_id' => $label->id, 'release_id' => $line['release_id'], 'track_id' => $line['track_id'], 'tool_role' => $line['role']];

        return Item::query()->withoutGlobalScope('label')->where($scope)->oldest('id')->first()
            ?? Item::query()->create([...$scope, 'name' => ($line['track_name'] ?? $line['release_name'])." [{$line['suffix']}]"]);
    }
}
