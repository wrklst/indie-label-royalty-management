<?php

use App\Models\Contract;
use App\Models\ContractSplit;
use App\Models\DataImport;
use App\Models\Item;
use App\Models\Label;
use App\Models\Ledger;
use App\Models\Payee;
use App\Models\Release;
use App\Models\Track;
use App\Models\Transaction;
use App\Services\Royalties\RoyaltyCalculator;
use App\Services\Royalties\Tools\MechanicalsTool;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    // A dollar label, so statutory rates apply without conversion.
    toolScenario(['currency' => 'USD']);
    $this->release->update(['accounts_mechanicals' => true]);
});

/**
 * @param  array<int, numeric-string>  $manufacturing
 * @return array<string, mixed>
 */
function mechanicalsReport(string $table = '2026', array $manufacturing = [], string $from = '2026-01-01', string $to = '2026-03-31'): array
{
    return app(MechanicalsTool::class)->report(test()->label->refresh(), $from, $to, $table, $manufacturing);
}

/**
 * The report rows keyed by track name.
 *
 * @param  array<string, mixed>  $report
 * @return array<string, array<string, mixed>>
 */
function mechanicalsRows(array $report): array
{
    return collect($report['rows'])->keyBy('track_name')->all();
}

function bookMechanicals(array $input = [])
{
    return test()->post(route('royalty-tools.mechanicals.store'), ['from' => '2026-01-01', 'to' => '2026-03-31', 'rates' => '2026', 'paid_on' => '2026-03-31', ...$input]);
}

test('a track uses its own rate, then the release rate, then the statutory rate', function () {
    $this->release->update(['mechanicals_rate' => '0.1']);
    $own = toolTrack('Own Rate', ['mechanicals_rate' => '0.2', 'duration' => 200]);
    $inherited = toolTrack('Release Rate', ['duration' => 200]);
    $other = Release::factory()->create(['label_id' => $this->label->id, 'accounts_mechanicals' => true]);
    $statutory = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $other->id, 'name' => 'Statutory Rate', 'duration' => 200]);

    toolSale(['track_id' => $own->id, 'quantity' => 10]);
    toolSale(['track_id' => $inherited->id, 'quantity' => 10]);
    toolSale(['release_id' => $other->id, 'track_id' => $statutory->id, 'quantity' => 10]);

    $report = mechanicalsReport();
    $rows = mechanicalsRows($report);

    expect($rows['Own Rate'])->toMatchArray(['rate' => '0.200000000000000', 'rate_source' => 'track', 'amount' => '2.000000000000000'])
        ->and($rows['Release Rate'])->toMatchArray(['rate' => '0.100000000000000', 'rate_source' => 'release', 'amount' => '1.000000000000000'])
        ->and($rows['Statutory Rate'])->toMatchArray(['rate' => '0.131000000000000', 'rate_source' => 'statutory', 'amount' => '1.310000000000000'])
        ->and($report['total'])->toBe('4.310000000000000')
        ->and($report['units'])->toBe('30.000000000000000');
});

test('the statutory rate is flat up to five minutes and per started minute above', function (string $table, ?int $seconds, ?string $expected) {
    expect(MechanicalsTool::statutoryRate($table, $seconds))->toBe($expected);
})->with([
    '2026, a short track' => ['2026', 200, '0.131000000000000'],
    '2026, exactly 5:00 is still flat' => ['2026', 300, '0.131000000000000'],
    '2026, 5:01 counts six minutes' => ['2026', 301, '0.151200000000000'],
    '2026, exactly 6:00 counts six minutes' => ['2026', 360, '0.151200000000000'],
    '2026, 6:01 counts seven minutes' => ['2026', 361, '0.176400000000000'],
    '2025, flat' => ['2025', 200, '0.127000000000000'],
    '2025, seven minutes' => ['2025', 420, '0.171500000000000'],
    '2024, flat' => ['2024', 200, '0.124000000000000'],
    '2024, seven minutes' => ['2024', 420, '0.167300000000000'],
    '2023, flat' => ['2023', 200, '0.120000000000000'],
    '2023, seven minutes' => ['2023', 420, '0.161700000000000'],
    'before 2023, flat' => ['pre-2023', 200, '0.091000000000000'],
    'before 2023, ten minutes' => ['pre-2023', 600, '0.175000000000000'],
    'no duration' => ['2026', null, null],
    'a duration of zero' => ['2026', 0, null],
    'an unknown table' => ['1999', 200, null],
]);

test('the rate table chosen for the run sets the statutory rate', function () {
    $track = toolTrack('Example Track', ['duration' => 200]);
    toolSale(['track_id' => $track->id, 'quantity' => 100]);

    expect(mechanicalsReport('2026')['total'])->toBe('13.100000000000000')
        ->and(mechanicalsReport('pre-2023')['total'])->toBe('9.100000000000000');
});

test('statutory rates are converted to the label currency and custom rates are not', function () {
    $this->label->update(['currency' => 'EUR', 'royalty_tool_settings' => ['usd_rate' => '0.9']]);
    $statutory = toolTrack('Statutory Rate', ['duration' => 300]);
    $custom = toolTrack('Own Rate', ['duration' => 300, 'mechanicals_rate' => '0.2']);
    toolSale(['track_id' => $statutory->id, 'quantity' => 10]);
    toolSale(['track_id' => $custom->id, 'quantity' => 10]);

    $rows = mechanicalsRows(mechanicalsReport());

    // 0.131 USD × 0.9 = 0.1179 EUR per unit.
    expect($rows['Statutory Rate'])->toMatchArray(['rate' => '0.117900000000000', 'amount' => '1.179000000000000'])
        ->and($rows['Own Rate'])->toMatchArray(['rate' => '0.200000000000000', 'amount' => '2.000000000000000']);
});

test('a statutory-rate track cannot be calculated without a conversion when the label does not account in dollars', function () {
    $this->label->update(['currency' => 'EUR']);
    $statutory = toolTrack('Statutory Rate', ['duration' => 300]);
    $custom = toolTrack('Own Rate', ['mechanicals_rate' => '0.2']);
    toolSale(['track_id' => $statutory->id, 'quantity' => 10]);
    toolSale(['track_id' => $custom->id, 'quantity' => 10]);

    $report = mechanicalsReport();

    expect(array_keys(mechanicalsRows($report)))->toBe(['Own Rate'])
        ->and($report['uncalculable'])->toHaveCount(1)
        ->and($report['uncalculable'][0])->toMatchArray([
            'track_name' => 'Statutory Rate',
            'reason' => 'The statutory rate is in US dollars and the label has no conversion to EUR.',
        ]);
});

test('a track with neither a duration nor a rate is listed as not calculable', function () {
    $track = toolTrack('No Duration');
    toolSale(['track_id' => $track->id, 'quantity' => 10]);

    $report = mechanicalsReport();

    expect($report['rows'])->toBe([])
        ->and($report['uncalculable'])->toBe([[
            'release_id' => $this->release->id, 'release_name' => 'Example Release', 'track_id' => $track->id, 'track_name' => 'No Duration',
            'reason' => 'No duration and no custom rate.',
        ]]);
});

test('a track setting overrides the release and an empty one follows it', function () {
    $follows = toolTrack('Follows Release', ['duration' => 200]);
    $out = toolTrack('Opted Out', ['duration' => 200, 'accounts_mechanicals' => false]);
    $off = Release::factory()->create(['label_id' => $this->label->id, 'accounts_mechanicals' => false]);
    $in = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $off->id, 'name' => 'Opted In', 'duration' => 200, 'accounts_mechanicals' => true]);
    $ignored = Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $off->id, 'name' => 'Follows Off', 'duration' => 200]);

    foreach ([[$this->release, $follows], [$this->release, $out], [$off, $in], [$off, $ignored]] as [$release, $track]) {
        toolSale(['release_id' => $release->id, 'track_id' => $track->id, 'quantity' => 10]);
    }

    expect(array_keys(mechanicalsRows(mechanicalsReport())))->toEqualCanonicalizing(['Follows Release', 'Opted In']);
});

test('only permanent downloads paid in the range count as units', function () {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);

    toolSale(['track_id' => $track->id, 'format' => 'Download Track', 'quantity' => 1, 'paid_timestamp' => '2026-01-01']);
    toolSale(['track_id' => $track->id, 'format' => 'Download Album', 'quantity' => 2, 'paid_timestamp' => '2026-03-31']);
    toolSale(['track_id' => $track->id, 'format' => 'Download/Other', 'quantity' => 4]);
    toolSale(['track_id' => $track->id, 'format' => 'Subscription Audio Stream', 'quantity' => 1000]);
    toolSale(['track_id' => $track->id, 'format' => 'LP', 'quantity' => 100]);
    toolSale(['track_id' => $track->id, 'format' => 'Download Track', 'quantity' => 100, 'paid_timestamp' => '2025-12-31']);
    toolSale(['track_id' => $track->id, 'format' => 'Download Track', 'quantity' => 100, 'paid_timestamp' => '2026-04-01']);
    toolSale(['track_id' => $track->id, 'format' => 'Download Track', 'quantity' => 100, 'type' => 'expense', 'total' => '-1']);

    expect(mechanicalsReport()['units'])->toBe('7.000000000000000');
});

test('physical sales count when the label includes them', function () {
    $this->label->update(['royalty_tool_settings' => ['include_physical' => true]]);
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'format' => 'Download Track', 'quantity' => 3]);
    toolSale(['track_id' => $track->id, 'format' => 'LP', 'quantity' => 5]);
    toolSale(['track_id' => $track->id, 'format' => 'Subscription Audio Stream', 'quantity' => 1000]);

    expect(mechanicalsReport()['units'])->toBe('8.000000000000000');
});

test('returns reduce the units unless the label ignores negative quantities', function () {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);
    toolSale(['track_id' => $track->id, 'quantity' => -3, 'total' => '-3']);

    expect(mechanicalsReport()['units'])->toBe('7.000000000000000');

    $this->label->update(['royalty_tool_settings' => ['ignore_negative_quantities' => true]]);

    expect(mechanicalsReport()['units'])->toBe('10.000000000000000');
});

test('a sale of the release counts once for each of its tracks that account mechanicals', function () {
    toolTrack('First Track', ['mechanicals_rate' => '0.1']);
    $second = toolTrack('Second Track', ['mechanicals_rate' => '0.2']);
    toolTrack('Opted Out', ['mechanicals_rate' => '0.5', 'accounts_mechanicals' => false]);

    toolSale(['format' => 'Download Album', 'quantity' => 10]);
    toolSale(['track_id' => $second->id, 'quantity' => 5]);

    $report = mechanicalsReport();
    $rows = mechanicalsRows($report);

    expect(array_keys($rows))->toBe(['First Track', 'Second Track'])
        ->and($rows['First Track'])->toMatchArray(['units' => '10.000000000000000', 'amount' => '1.000000000000000'])
        ->and($rows['Second Track'])->toMatchArray(['units' => '15.000000000000000', 'amount' => '3.000000000000000'])
        ->and($report['total'])->toBe('4.000000000000000');
});

test('a manufacturing run adds its copies to every track of the release that accounts mechanicals', function () {
    $first = toolTrack('First Track', ['mechanicals_rate' => '0.1']);
    toolTrack('Second Track', ['mechanicals_rate' => '0.2']);
    toolTrack('Opted Out', ['mechanicals_rate' => '0.5', 'accounts_mechanicals' => false]);
    toolSale(['track_id' => $first->id, 'quantity' => 4]);

    $rows = mechanicalsRows(mechanicalsReport(manufacturing: [$this->release->id => '500']));

    expect(array_keys($rows))->toBe(['First Track', 'Second Track'])
        ->and($rows['First Track'])->toMatchArray(['sold' => '4.000000000000000', 'manufactured' => '500.000000000000000', 'units' => '504.000000000000000', 'amount' => '50.400000000000000'])
        ->and($rows['Second Track'])->toMatchArray(['sold' => '0.000000000000000', 'units' => '500.000000000000000', 'amount' => '100.000000000000000']);
});

test('a manufacturing run for a release without mechanicals is reported as unused', function () {
    $other = Release::factory()->create(['label_id' => $this->label->id, 'name' => 'Other Release']);
    Track::factory()->create(['label_id' => $this->label->id, 'release_id' => $other->id, 'mechanicals_rate' => '1']);

    $report = mechanicalsReport(manufacturing: [$other->id => '500']);

    expect($report['rows'])->toBe([])
        ->and($report['unused_manufacturing'])->toBe(['Other Release']);
});

test('sales without a converted amount or a quantity are not counted and are reported', function () {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);
    toolSale(['track_id' => $track->id, 'quantity' => 5, 'converted_total' => null]);
    toolSale(['track_id' => $track->id, 'quantity' => null]);
    toolSale(['track_id' => $track->id, 'quantity' => null]);

    $report = mechanicalsReport();

    expect($report['units'])->toBe('10.000000000000000')
        ->and($report['unconverted'])->toBe(1)
        ->and($report['without_quantity'])->toBe(2);
});

test('sales of another label are not counted', function () {
    toolTrack('Example Track', ['mechanicals_rate' => '1']);
    $foreign = Release::factory()->create(['accounts_mechanicals' => true]);
    $foreignTrack = Track::factory()->create(['label_id' => $foreign->label_id, 'release_id' => $foreign->id, 'mechanicals_rate' => '1']);
    Transaction::factory()->create(['label_id' => $foreign->label_id, 'release_id' => $foreign->id, 'track_id' => $foreignTrack->id, 'format' => 'Download Track', 'quantity' => 50, 'paid_timestamp' => '2026-02-15']);

    expect(mechanicalsReport()['rows'])->toBe([]);
});

test('the page shows the report for the chosen range', function () {
    $track = toolTrack('Example Track', ['duration' => 200]);
    toolSale(['track_id' => $track->id, 'quantity' => 100]);

    $this->get(route('royalty-tools.mechanicals.index', ['from' => '2026-01-01', 'to' => '2026-03-31', 'rates' => '2025']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('royalty-tools/mechanicals')
            ->where('currency', 'USD')
            ->where('filters.rates', '2025')
            ->where('report.total', '12.700000000000000')
            ->where('report.rows.0.track_name', 'Example Track')
            ->where('overlaps', [])
            ->where('runs', []));
});

test('the page shows no report before a range is chosen', function () {
    $this->get(route('royalty-tools.mechanicals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('report', null)->has('rateTables', 5));
});

test('creating transactions books an expense and an income line per track as one import', function () {
    $track = toolTrack('Example Track', ['duration' => 200]);
    toolSale(['track_id' => $track->id, 'quantity' => 100]);

    bookMechanicals(['paid_on' => '2026-03-20'])
        ->assertRedirect(route('royalty-tools.mechanicals.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', '2 transactions created.');

    $import = DataImport::query()->where('importer', 'mechanicals-tool')->sole();
    $booked = toolBooked();

    expect($import->name)->toBe('Mechanicals 2026-01-01 – 2026-03-31')
        ->and($import->status->value)->toBe('imported')
        ->and($import->transactions_count)->toBe(2)
        ->and($import->user_id)->toBe($this->admin->id)
        ->and($import->options)->toMatchArray(['from' => '2026-01-01', 'to' => '2026-03-31', 'rates' => '2026', 'total' => '13.100000000000000'])
        ->and($booked)->toHaveCount(2);

    expect($booked['mechanicals-expenses'])
        ->type->value->toBe('expense')
        ->converted_total->toBe('-13.100000000000000')
        ->total->toBe('-13.100000000000000')
        ->currency->toBe('USD')
        ->category->toBe('Mechanicals')
        ->recoupable->toBeTrue()
        ->release_id->toBe($this->release->id)
        ->track_id->toBe($track->id)
        ->data_import_id->toBe($import->id)
        ->importer_source->toBe('mechanicals-tool')
        ->memo->toBe('Mechanicals: 100 units × 0.131')
        ->and($booked['mechanicals-expenses']->paid_timestamp->toDateString())->toBe('2026-03-20')
        ->and($booked['mechanicals-expenses']->item->name)->toBe('Example Track [Mechanicals Expenses]');

    expect($booked['mechanicals-income'])
        ->type->value->toBe('income')
        ->converted_total->toBe('13.100000000000000')
        ->category->toBe('Mechanicals')
        ->track_id->toBe($track->id)
        ->and($booked['mechanicals-income']->paid_timestamp->toDateString())->toBe('2026-03-20')
        ->and($booked['mechanicals-income']->item)
        ->name->toBe('Example Track [Mechanicals Income]')
        ->contract_id->toBeNull()
        ->is_additionals->toBeFalse()
        ->release_id->toBe($this->release->id)
        ->track_id->toBe($track->id);
});

test('the pair cancels out for everyone under the release contract', function () {
    $track = toolTrack('Example Track', ['duration' => 200]);
    toolSale(['track_id' => $track->id, 'quantity' => 100, 'total' => '100']);

    $before = toolPayeeTotals($this->firstQuarter, $this->artist);
    bookMechanicals()->assertSessionHasNoErrors();
    $after = toolPayeeTotals($this->firstQuarter, $this->artist);

    expect($before['payee_share'])->toBe('50.000000000000000')
        ->and($after['payee_share'])->toBe('50.000000000000000')
        ->and($after['net'])->toBe('100.000000000000000');
});

test('a publishing contract on the income item pays the songwriter on their own ledger', function () {
    $track = toolTrack('Example Track', ['duration' => 200]);
    toolSale(['track_id' => $track->id, 'quantity' => 100, 'total' => '100']);
    bookMechanicals()->assertSessionHasNoErrors();

    $songwriter = Payee::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Songwriter']);
    $ledger = Ledger::factory()->create(['label_id' => $this->label->id, 'name' => 'Publishing']);
    $publishing = Contract::factory()->create(['label_id' => $this->label->id, 'name' => 'Example Publishing Deal']);
    ContractSplit::factory()->create(['contract_id' => $publishing->id, 'payee_id' => $songwriter->id, 'ledger_id' => $ledger->id, 'split_amount' => '1']);

    $this->put(route('items.update', toolItem('mechanicals-income')), ['contract_id' => $publishing->id, 'release_id' => $this->release->id, 'track_id' => $track->id])
        ->assertSessionHasNoErrors();

    $report = collect(app(RoyaltyCalculator::class)->forPeriod($this->firstQuarter)['payees'])->keyBy('payee_name');

    // The songwriter is owed 100 × 0.131 = 13.10. The artist bears half of
    // that cost under the release contract: (100 − 13.10) × 50% = 43.45.
    expect($report['Example Songwriter']['totals']['payee_share'])->toBe('13.100000000000000')
        ->and($report['Example Songwriter']['ledgers'][0]['ledger_name'])->toBe('Publishing')
        ->and($report['Example Songwriter']['ledgers'][0]['balance'])->toBe('13.100000000000000')
        ->and($report['Example Artist']['totals']['payee_share'])->toBe('43.450000000000000');
});

test('a second run books on the items of the first', function () {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);
    toolSale(['track_id' => $track->id, 'quantity' => 20, 'paid_timestamp' => '2026-05-15']);

    bookMechanicals()->assertSessionHasNoErrors();
    bookMechanicals(['from' => '2026-04-01', 'to' => '2026-06-30', 'paid_on' => '2026-06-30'])->assertSessionHasNoErrors();

    expect(Item::query()->whereNotNull('tool_role')->count())->toBe(2)
        ->and(toolItem('mechanicals-income')->transactions()->pluck('converted_total')->all())->toBe(['10.000000000000000', '20.000000000000000']);
});

test('what the tool booked is never counted as a sale again', function () {
    $this->label->update(['royalty_tool_settings' => ['include_physical' => true]]);
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);
    bookMechanicals()->assertSessionHasNoErrors();

    // Even if someone gives the booked income a counted format and a quantity.
    Transaction::query()->where('importer_source', 'mechanicals-tool')->update(['format' => 'LP', 'quantity' => 99]);

    expect(mechanicalsReport()['units'])->toBe('10.000000000000000');
});

test('an overlapping range is flagged on the page and needs a confirmation to be booked again', function () {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);
    bookMechanicals()->assertSessionHasNoErrors();
    $first = DataImport::query()->sole();

    $this->get(route('royalty-tools.mechanicals.index', ['from' => '2026-03-31', 'to' => '2026-04-30']))
        ->assertInertia(fn (Assert $page) => $page->has('overlaps', 1)->where('overlaps.0.id', $first->id)->where('runs.0.total', '10.000000000000000'));

    $this->get(route('royalty-tools.mechanicals.index', ['from' => '2026-04-01', 'to' => '2026-04-30']))
        ->assertInertia(fn (Assert $page) => $page->where('overlaps', [])->has('runs', 1));

    bookMechanicals(['from' => '2026-02-01', 'to' => '2026-02-28'])
        ->assertSessionHasErrors(['confirm_overlap' => 'An earlier run covers part of this range. Confirm that you want to book it again.']);

    expect(DataImport::query()->count())->toBe(1)
        ->and(Transaction::query()->where('importer_source', 'mechanicals-tool')->count())->toBe(2);

    bookMechanicals(['from' => '2026-01-01', 'to' => '2026-03-31', 'confirm_overlap' => '1'])->assertSessionHasNoErrors();

    expect(DataImport::query()->count())->toBe(2)
        ->and(Transaction::query()->where('importer_source', 'mechanicals-tool')->count())->toBe(4);
});

test('deleting the import of a run removes its transactions and frees the range', function () {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);
    bookMechanicals()->assertSessionHasNoErrors();

    $this->delete(route('data-imports.destroy', DataImport::query()->sole()))->assertRedirect(route('data-imports.index'));

    expect(Transaction::query()->where('importer_source', 'mechanicals-tool')->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(1);

    $this->get(route('royalty-tools.mechanicals.index', ['from' => '2026-01-01', 'to' => '2026-03-31']))
        ->assertInertia(fn (Assert $page) => $page->where('runs', [])->where('overlaps', []));

    bookMechanicals()->assertSessionHasNoErrors();
});

test('nothing is booked when the range has no mechanicals', function () {
    toolTrack('Example Track', ['mechanicals_rate' => '1']);

    bookMechanicals()->assertSessionHasErrors(['from' => 'There is nothing to book for this range.']);

    $this->assertDatabaseCount('data_imports', 0);
});

test('a run is refused when its input is invalid', function (array $input, string $field) {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);

    bookMechanicals($input)->assertSessionHasErrors($field);

    $this->assertDatabaseCount('data_imports', 0);
})->with([
    'no start' => [['from' => ''], 'from'],
    'an end before the start' => [['to' => '2025-12-31'], 'to'],
    'a start that is not a date' => [['from' => 'January'], 'from'],
    'no paid date' => [['paid_on' => ''], 'paid_on'],
    'an unknown rate table' => [['rates' => '1999'], 'rates'],
    'a manufacturing run of zero copies' => [fn () => ['manufacturing' => [['release_id' => test()->release->id, 'quantity' => 0]]], 'manufacturing.0.quantity'],
    'a manufacturing run without a release' => [['manufacturing' => [['release_id' => '', 'quantity' => 100]]], 'manufacturing.0.release_id'],
    'a manufacturing run for a release of another label' => [fn () => ['manufacturing' => [['release_id' => Release::factory()->create()->id, 'quantity' => 100]]], 'manufacturing.0.release_id'],
]);

test('manufacturing runs entered for the run are booked', function () {
    toolTrack('Example Track', ['mechanicals_rate' => '0.1']);

    bookMechanicals(['manufacturing' => [['release_id' => $this->release->id, 'quantity' => '500']]])->assertSessionHasNoErrors();

    expect(toolBooked()['mechanicals-income']->converted_total)->toBe('50.000000000000000')
        ->and(DataImport::query()->sole()->options['manufacturing'])->toBe([$this->release->id => '500']);
});

test('the tool does not exist for a label that has mechanicals switched off', function () {
    $this->label->update(['accounts_mechanicals' => false]);

    $this->get(route('royalty-tools.mechanicals.index'))->assertNotFound();
    bookMechanicals()->assertNotFound();
    $this->get(route('royalty-tools.rates.edit', ['mechanicals', $this->release]))->assertNotFound();
});

test('a payee cannot use the tool', function () {
    $this->actingAs(toolPayeeUser($this->label));

    $this->get(route('royalty-tools.mechanicals.index'))->assertRedirect(route('portal.index'));
    bookMechanicals()->assertForbidden();
});

test('a guest is sent to sign in', function () {
    auth()->logout();

    $this->get(route('royalty-tools.mechanicals.index'))->assertRedirect(route('login'));
});

test('an admin of another label sees neither the runs nor the sales of this one', function () {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);
    bookMechanicals()->assertSessionHasNoErrors();

    $other = Label::factory()->create(['accounts_mechanicals' => true]);

    $this->actingAs(labelAdmin($other))
        ->get(route('royalty-tools.mechanicals.index', ['from' => '2026-01-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('runs', [])->where('overlaps', [])->where('report.rows', []));
});

test('the import of a run opens and is listed like any other import', function () {
    $track = toolTrack('Example Track', ['mechanicals_rate' => '1']);
    toolSale(['track_id' => $track->id, 'quantity' => 10]);
    bookMechanicals()->assertSessionHasNoErrors();
    $import = DataImport::query()->sole();

    $this->get(route('data-imports.show', $import))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('import.name', 'Mechanicals 2026-01-01 – 2026-03-31')
            ->where('import.transactions_count', 2)
            ->where('import.default_paid_timestamp', '2026-03-31'));

    $this->get(route('data-imports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('imports.data.0.id', $import->id));
});
