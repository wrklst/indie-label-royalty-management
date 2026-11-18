<?php

use App\Models\Label;
use App\Models\Payee;
use App\Models\Period;
use App\Models\Release;
use App\Models\Transaction;

test('label admins can open every page', function (string $route) {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->get(route($route))
        ->assertOk();
})->with([
    'dashboard', 'catalog.index', 'artists.index', 'releases.index', 'releases.create', 'tracks.index', 'items.index',
    'contracts.index', 'contracts.create', 'payees.index', 'ledgers.index', 'transactions.index', 'transactions.create',
    'data-imports.index', 'data-imports.create', 'periods.index', 'statements.index', 'payouts.index', 'portal.index',
    'label-settings.edit', 'team.index', 'labels.create',
]);

test('detail pages render for records with activity', function () {
    $label = Label::factory()->create();
    $release = Release::factory()->create(['label_id' => $label->id]);
    $payee = Payee::factory()->create(['label_id' => $label->id]);
    $period = Period::factory()->create(['label_id' => $label->id]);
    $transaction = Transaction::factory()->create(['label_id' => $label->id, 'release_id' => $release->id]);
    $this->actingAs(labelAdmin($label));

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('releases.show', $release->id))->assertOk();
    $this->get(route('releases.edit', $release->id))->assertOk();
    $this->get(route('payees.show', $payee->id))->assertOk();
    $this->get(route('periods.show', $period->id))->assertOk();
    $this->get(route('periods.payee', [$period->id, $payee->id]))->assertOk();
    $this->get(route('transactions.edit', $transaction->id))->assertOk();
});

test('the template download lists the supported columns', function () {
    $response = $this->actingAs(labelAdmin(Label::factory()->create()))->get(route('data-imports.template'))->assertOk();

    expect($response->streamedContent())->toContain('paid timestamp')->toContain('isrc');
});
