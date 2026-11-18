<?php

use App\Models\Label;
use App\Models\Release;
use App\Models\Transaction;

test('clicking a column header of a paginated list sorts it, reverses it, then restores the default order', function () {
    $label = Label::factory()->create();
    Release::factory()->create(['label_id' => $label->id, 'name' => 'Bravo Release', 'catalog_number' => 'EX001']);
    Release::factory()->create(['label_id' => $label->id, 'name' => 'Charlie Release', 'catalog_number' => 'EX002']);
    Release::factory()->create(['label_id' => $label->id, 'name' => 'Alpha Release', 'catalog_number' => 'EX003']);
    $this->actingAs(labelAdmin($label));

    $firstRow = '[data-slot="table-body"] tr:first-child';
    $lastRow = '[data-slot="table-body"] tr:last-child';

    visit('/releases?search=Release')
        ->assertSeeIn($firstRow, 'Bravo Release')
        ->assertAttribute('[data-sort="name"]', 'aria-sort', 'none')
        ->click('[data-sort="name"] button')
        ->assertSeeIn($firstRow, 'Alpha Release')
        ->assertSeeIn($lastRow, 'Charlie Release')
        ->assertAttribute('[data-sort="name"]', 'aria-sort', 'ascending')
        ->assertQueryStringHas('sort', 'name')
        ->assertQueryStringHas('direction', 'asc')
        ->assertQueryStringHas('search', 'Release')
        ->click('[data-sort="name"] button')
        ->assertSeeIn($firstRow, 'Charlie Release')
        ->assertSeeIn($lastRow, 'Alpha Release')
        ->assertAttribute('[data-sort="name"]', 'aria-sort', 'descending')
        ->assertQueryStringHas('direction', 'desc')
        ->click('[data-sort="name"] button')
        ->assertSeeIn($firstRow, 'Bravo Release')
        ->assertSeeIn($lastRow, 'Alpha Release')
        ->assertAttribute('[data-sort="name"]', 'aria-sort', 'none')
        ->assertQueryStringMissing('sort')
        ->assertQueryStringMissing('direction')
        ->assertQueryStringHas('search', 'Release')
        ->assertNoJavaScriptErrors();
});

test('a table that is complete in the browser sorts by amount and keeps its special rows and totals in place', function () {
    $label = Label::factory()->create();
    $small = Release::factory()->create(['label_id' => $label->id, 'name' => 'Alpha Release', 'catalog_number' => 'EX001']);
    $large = Release::factory()->create(['label_id' => $label->id, 'name' => 'Bravo Release', 'catalog_number' => 'EX002']);
    $middle = Release::factory()->create(['label_id' => $label->id, 'name' => 'Charlie Release', 'catalog_number' => 'EX003']);
    Transaction::factory()->create(['label_id' => $label->id, 'release_id' => $small->id, 'total' => '9']);
    Transaction::factory()->create(['label_id' => $label->id, 'release_id' => $large->id, 'total' => '100']);
    Transaction::factory()->create(['label_id' => $label->id, 'release_id' => $middle->id, 'total' => '20']);
    Transaction::factory()->create(['label_id' => $label->id, 'release_id' => null, 'total' => '500']);
    $this->actingAs(labelAdmin($label));

    $row = fn (int $position): string => "[data-slot=\"table-body\"] tr:nth-child({$position})";

    visit('/profitability')
        ->assertSeeIn($row(4), 'Not assigned to a release')
        ->click('[data-sort="income"] button')
        ->assertSeeIn($row(1), 'Bravo Release')
        ->assertSeeIn($row(2), 'Charlie Release')
        ->assertSeeIn($row(3), 'Alpha Release')
        ->assertSeeIn($row(4), 'Not assigned to a release')
        ->assertSeeIn('[data-slot="table-footer"] td:first-child', 'Total')
        ->assertAttribute('[data-sort="income"]', 'aria-sort', 'descending')
        ->click('[data-sort="income"] button')
        ->assertSeeIn($row(1), 'Alpha Release')
        ->assertSeeIn($row(3), 'Bravo Release')
        ->assertSeeIn($row(4), 'Not assigned to a release')
        ->click('[data-sort="release"] button')
        ->assertSeeIn($row(1), 'Alpha Release')
        ->assertSeeIn($row(2), 'Bravo Release')
        ->assertSeeIn($row(3), 'Charlie Release')
        ->assertSeeIn($row(4), 'Not assigned to a release')
        ->assertPathIs('/profitability')
        ->assertQueryStringMissing('sort')
        ->assertNoJavaScriptErrors();
});
