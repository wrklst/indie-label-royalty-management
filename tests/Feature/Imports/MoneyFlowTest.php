<?php

use App\Models\Period;
use App\Models\Release;
use App\Models\Transaction;
use App\Services\Imports\ImportRunner;
use App\Services\Royalties\MoneyFlow;
use Inertia\Testing\AssertableInertia as Assert;

test('a zebralution line keeps what the shop paid and the share the distributor kept', function () {
    $label = currentLabel();

    app(ImportRunner::class)->run(pendingImport($label, 'zebralution', 'zebralution_2026q3_sample.csv', ['default_paid_timestamp' => '2026-10-15']), $label);

    $line = Transaction::query()->where('retailer', 'Anghami')->where('total', '0.001201168')->firstOrFail();

    // 80% contract share of 0.00150146 distributable.
    expect($line->gross_total)->toBe('0.001501460000000')
        ->and($line->distribution_fee)->toBe('0.000300292000000')
        ->and($line->payment_fee)->toBeNull();
});

test('a bandcamp sale keeps what the buyer paid, the payment fee and the share bandcamp kept', function () {
    $label = currentLabel();

    app(ImportRunner::class)->run(pendingImport($label, 'bandcamp', 'bandcamp_raw_sample.csv'), $label);

    $track = Transaction::query()->where('format', 'Download Track')->sole();

    // Buyer paid 1.80, 1.67 arrived after the payment provider, 1.14 after bandcamp.
    expect($track->gross_total)->toBe('1.800000000000000')
        ->and($track->payment_fee)->toBe('0.130000000000000')
        ->and($track->distribution_fee)->toBe('0.530000000000000');
});

test('the money flow sums deductions in the label currency and keeps income without a breakdown apart', function () {
    $label = currentLabel();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $line = fn (array $attributes): Transaction => Transaction::factory()->create([
        'label_id' => $label->id, 'release_id' => $release->id, 'type' => 'income', 'currency' => 'EUR', 'conversion_rate' => '1',
        'paid_timestamp' => '2026-04-15', ...$attributes,
    ]);
    $line(['total' => '70', 'converted_total' => '70', 'gross_total' => '100', 'distribution_fee' => '20', 'payment_fee' => '4']);
    // 10 USD gross at 0.5: 5 gross, 1 kept by the shop, 4 received.
    $line(['currency' => 'USD', 'conversion_rate' => '0.5', 'total' => '8', 'converted_total' => '4', 'gross_total' => '10', 'distribution_fee' => '2']);
    $line(['total' => '30', 'converted_total' => '30']);
    $line(['type' => 'expense', 'total' => '-50', 'converted_total' => '-50']);
    $line(['currency' => 'USD', 'conversion_rate' => null, 'total' => '9', 'converted_total' => null, 'gross_total' => '12']);

    $flow = MoneyFlow::of(Transaction::query());

    expect(array_map(fn (mixed $value): mixed => is_string($value) ? (float) $value : $value, $flow))->toBe([
        'gross' => 105.0, 'distribution_fee' => 21.0, 'payment_fee' => 4.0, 'other_deductions' => 6.0,
        'received_with_breakdown' => 74.0, 'received_without_breakdown' => 30.0, 'received' => 104.0,
        'lines_with_breakdown' => 2, 'lines_without_breakdown' => 1,
    ]);
});

test('a period shows the money flow of the income paid in it', function () {
    $scenario = royaltyScenario();
    $period = Period::factory()->create(['label_id' => $scenario['label']->id, 'starts_on' => '2026-04-01', 'ends_on' => '2026-06-30']);
    Transaction::factory()->create([
        'label_id' => $scenario['label']->id, 'release_id' => $scenario['release']->id, 'type' => 'income', 'currency' => 'EUR', 'conversion_rate' => '1',
        'paid_timestamp' => '2026-05-15', 'total' => '70', 'converted_total' => '70', 'gross_total' => '100', 'distribution_fee' => '20', 'payment_fee' => '4',
    ]);
    Transaction::factory()->create([
        'label_id' => $scenario['label']->id, 'release_id' => $scenario['release']->id, 'type' => 'income', 'currency' => 'EUR', 'conversion_rate' => '1',
        'paid_timestamp' => '2026-08-15', 'total' => '50', 'converted_total' => '50', 'gross_total' => '60', 'distribution_fee' => '10',
    ]);

    $this->actingAs($scenario['admin'])->get(route('periods.show', $period))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flow.lines_with_breakdown', 1)
            ->where('flow.gross', fn (string $gross): bool => (float) $gross === 100.0)
            ->where('flow.distribution_fee', fn (string $fee): bool => (float) $fee === 20.0));
});
