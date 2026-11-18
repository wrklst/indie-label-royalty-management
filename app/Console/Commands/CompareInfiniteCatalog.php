<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Models\Payout;
use App\Models\Period;
use App\Services\Royalties\RoyaltyCalculator;
use App\Support\CurrentLabel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('royalties:compare-infinite-catalog
    {directory : Folder holding the export, including summaries.json}
    {--label= : Slug or id of the label the export was imported into}
    {--tolerance=0.000001 : Largest difference still counted as equal}
    {--show=40 : How many differences to list}')]
#[Description('Check the calculated period figures against the ones Infinite Catalog reported')]
class CompareInfiniteCatalog extends Command
{
    /** @var array<string, array{checked: int, differing: int, largest: float}> */
    private array $results = [];

    /** @var list<array<int, string>> */
    private array $differences = [];

    public function handle(RoyaltyCalculator $calculator, CurrentLabel $currentLabel): int
    {
        $path = rtrim((string) $this->argument('directory'), '/').'/summaries.json';
        $label = Label::query()->where(ctype_digit((string) $this->option('label')) ? 'id' : 'slug', $this->option('label'))->first();

        if ($label === null || ! is_file($path)) {
            $this->error('Pass --label and a directory containing summaries.json.');

            return self::FAILURE;
        }

        $currentLabel->set($label);
        $summaries = $this->summaries($path);
        $payouts = Payout::query()->with('period:id,ends_on')->get();

        foreach (collect($summaries)->sortBy('period.start') as $theirs) {
            $period = Period::query()->where('name', $theirs['period']['name'])->first();

            if ($period === null) {
                $this->warn("Period {$theirs['period']['name']} does not exist here.");

                continue;
            }

            $report = collect($calculator->forPeriod($period)['payees'])->keyBy('payee_name');
            $start = $period->starts_on->toDateString();
            $end = $period->ends_on->toDateString();

            foreach ($theirs['summary']['reports'][0]['data']['rows'] as $row) {
                $name = $row['payee_name'];
                $mine = $report[$name] ?? null;
                $totals = $mine['totals'] ?? [];
                $context = "{$period->name} · {$name}";

                // Infinite Catalog deducts a payout on the day it was paid;
                // here it is deducted in the period it settles. To compare
                // balances like for like, the payouts are re-dated their way.
                $mineOfPayee = $payouts->where('payee_id', $mine['payee_id'] ?? 0);
                $settledEarlier = (float) $mineOfPayee->filter(fn (Payout $payout): bool => $payout->period->ends_on->toDateString() < $start)->sum('total');
                $paidEarlier = (float) $mineOfPayee->filter(fn (Payout $payout): bool => $payout->paid_on->toDateString() < $start)->sum('total');
                $paidWithin = (float) $mineOfPayee->filter(fn (Payout $payout): bool => $payout->paid_on->toDateString() >= $start && $payout->paid_on->toDateString() <= $end)->sum('total');
                $previousTheirWay = (float) ($totals['previous_balance'] ?? 0) + $settledEarlier - $paidEarlier;
                $share = (float) ($totals['payee_share'] ?? 0);
                $transfers = (float) ($totals['transfers'] ?? 0);

                $this->check('Payee share per period', $context, $row['payee_share'], $share);
                $this->check('Transfers per period', $context, $row['non_payout_transfers'], $transfers);
                $this->check('Paid out for the period', $context, $row['total_paid'] ?? 0, (float) ($totals['total_paid'] ?? 0));
                $this->check('Previous payable (payouts dated the IC way)', $context, $row['previous_payable'], max($previousTheirWay, 0));
                $this->check('Total payable (payouts dated the IC way)', $context, $row['total_payable'], max($previousTheirWay + $share + $transfers - $paidWithin, 0));

                $ledgers = $theirs['payees'][$row['payee_id']]['reports']['ledger_summary']['ledgers'] ?? [];
                $this->compareLedgers($context, $ledgers, $mine['ledgers'] ?? [], $settledEarlier - $paidEarlier, $paidWithin);
            }
        }

        $this->table(
            ['Figure', 'Values checked', 'Differing', 'Largest difference'],
            collect($this->results)->map(fn (array $result, string $figure): array => [
                $figure, $result['checked'], $result['differing'], rtrim(rtrim(number_format($result['largest'], 12), '0'), '.') ?: '0',
            ])->values()->all(),
        );

        if ($this->differences !== []) {
            $this->newLine();
            $this->table(['Figure', 'Where', 'Infinite Catalog', 'Here', 'Difference'], array_slice($this->differences, 0, (int) $this->option('show')));
        }

        return collect($this->results)->sum('differing') === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The period summaries exported from Infinite Catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    private function summaries(string $path): array
    {
        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<array<string, mixed>>  $theirs
     * @param  list<array<string, mixed>>  $mine
     */
    private function compareLedgers(string $context, array $theirs, array $mine, float $payoutShift, float $paidWithin): void
    {
        $mine = collect($mine)->keyBy('ledger_name');
        $single = count($theirs) === 1 && $mine->count() <= 1;

        foreach ($theirs as $ledger) {
            $own = $mine[$ledger['ledger_name']] ?? [];
            $where = "{$context} · {$ledger['ledger_name']}";

            foreach (['income', 'expense', 'net', 'payee_share', 'transfers'] as $field) {
                $this->check("Ledger {$field}", $where, $ledger[$field], (float) ($own[$field] ?? 0));
            }

            // Payouts are booked on the primary ledger here, so re-dating
            // them is only unambiguous for payees with a single ledger.
            if ($single) {
                $previous = (float) ($own['previous_balance'] ?? 0) + $payoutShift;
                $this->check('Ledger previous balance (payouts dated the IC way)', $where, $ledger['previous_balance'], $previous);
                $this->check('Ledger balance (payouts dated the IC way)', $where, $ledger['balance'], $previous + (float) ($own['payee_share'] ?? 0) + (float) ($own['transfers'] ?? 0) - $paidWithin);
            }

            $theirTracks = [];
            $ownTracks = [];

            foreach ($ledger['releases'] as $release) {
                foreach ($release['tracks'] as $track) {
                    $key = implode(' / ', [$release['release_name'], $track['track_name'], $track['contract_name']]);
                    $theirTracks[$key]['income'] = ($theirTracks[$key]['income'] ?? 0) + $track['income'];
                    $theirTracks[$key]['payee_share'] = ($theirTracks[$key]['payee_share'] ?? 0) + $track['payee_share'];
                    $theirTracks[$key]['split'] = $track['split_amount'];
                }
            }

            foreach ($own['releases'] ?? [] as $release) {
                foreach ($release['tracks'] as $track) {
                    $key = implode(' / ', [$release['release_name'], $track['track_name'], $track['contract_name']]);
                    $ownTracks[$key]['income'] = ($ownTracks[$key]['income'] ?? 0) + (float) $track['income'];
                    $ownTracks[$key]['payee_share'] = ($ownTracks[$key]['payee_share'] ?? 0) + (float) $track['payee_share'];
                    $ownTracks[$key]['split'] = (float) $track['split'];
                }
            }

            foreach (array_unique([...array_keys($theirTracks), ...array_keys($ownTracks)]) as $key) {
                $this->check('Track income', "{$where} · {$key}", $theirTracks[$key]['income'] ?? 0, $ownTracks[$key]['income'] ?? 0);
                $this->check('Track payee share', "{$where} · {$key}", $theirTracks[$key]['payee_share'] ?? 0, $ownTracks[$key]['payee_share'] ?? 0);

                if (isset($theirTracks[$key], $ownTracks[$key])) {
                    $this->check('Track split', "{$where} · {$key}", $theirTracks[$key]['split'], $ownTracks[$key]['split']);
                }
            }
        }
    }

    private function check(string $figure, string $where, int|float|null $theirs, float $mine): void
    {
        $theirs = (float) $theirs;
        $difference = abs($theirs - $mine);

        $this->results[$figure] ??= ['checked' => 0, 'differing' => 0, 'largest' => 0.0];
        $this->results[$figure]['checked']++;
        $this->results[$figure]['largest'] = max($this->results[$figure]['largest'], $difference);

        if ($difference > (float) $this->option('tolerance')) {
            $this->results[$figure]['differing']++;
            $this->differences[] = [$figure, $where, (string) round($theirs, 6), (string) round($mine, 6), (string) round($mine - $theirs, 6)];
        }
    }
}
