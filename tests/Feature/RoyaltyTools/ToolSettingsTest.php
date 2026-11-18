<?php

use App\Models\DataImport;
use App\Models\Label;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Helpers.php';

test('the label settings page shows the settings of the tools', function () {
    $label = Label::factory()->create(['royalty_tool_settings' => ['usd_rate' => '0.92', 'include_physical' => true, 'ignore_negative_quantities' => false]]);

    $this->actingAs(labelAdmin($label))
        ->get(route('label-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('royaltyTools', [
            'mechanicals_usd_rate' => '0.92', 'mechanicals_include_physical' => true, 'ignore_negative_quantities' => false,
        ]));
});

test('the tool settings are off for a label that never saved them', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->get(route('label-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('royaltyTools', [
            'mechanicals_usd_rate' => null, 'mechanicals_include_physical' => false, 'ignore_negative_quantities' => false,
        ]));
});

test('the tool settings are saved with the label settings', function () {
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), [
            'name' => 'Example Records', 'currency' => 'EUR', 'accounts_mechanicals' => '1', 'accounts_ppd' => '1', 'accounts_reserves' => '0',
            'mechanicals_usd_rate' => '0.92', 'mechanicals_include_physical' => '1', 'ignore_negative_quantities' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($label->refresh())
        ->accounts_mechanicals->toBeTrue()
        ->accounts_reserves->toBeFalse()
        ->royalty_tool_settings->toBe(['usd_rate' => '0.92', 'include_physical' => true, 'ignore_negative_quantities' => true]);
});

test('a form that does not send the tool settings leaves them as they are', function () {
    $label = Label::factory()->create(['currency' => 'EUR', 'royalty_tool_settings' => ['usd_rate' => '0.92', 'include_physical' => true, 'ignore_negative_quantities' => true]]);

    $this->actingAs(labelAdmin($label))
        ->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR'])
        ->assertSessionHasNoErrors();

    expect($label->refresh()->royalty_tool_settings)->toMatchArray(['include_physical' => true, 'ignore_negative_quantities' => true])
        ->and((float) $label->royalty_tool_settings['usd_rate'])->toBe(0.92);
});

test('the conversion can be cleared and must be a positive number', function () {
    $label = Label::factory()->create(['currency' => 'EUR', 'royalty_tool_settings' => ['usd_rate' => '0.92']]);
    $this->actingAs(labelAdmin($label));

    $this->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'mechanicals_usd_rate' => '0'])
        ->assertSessionHasErrors('mechanicals_usd_rate');
    $this->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'mechanicals_usd_rate' => 'about one'])
        ->assertSessionHasErrors('mechanicals_usd_rate');

    $this->put(route('label-settings.update'), ['name' => 'Example Records', 'currency' => 'EUR', 'mechanicals_usd_rate' => ''])
        ->assertSessionHasNoErrors();

    expect($label->refresh()->royalty_tool_settings['usd_rate'])->toBeNull();
});

test('every page is told which tools the label uses, so menus and rate fields follow the switches', function () {
    $label = Label::factory()->create(['accounts_mechanicals' => true, 'accounts_ppd' => false, 'accounts_reserves' => true]);

    $this->actingAs(labelAdmin($label))
        ->get(route('releases.create'))
        ->assertInertia(fn (Assert $page) => $page->where('label.tools', ['mechanicals' => true, 'ppd' => false, 'reserves' => true]));
});

test('a label has every tool switched off until it switches one on', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('label.tools', ['mechanicals' => false, 'ppd' => false, 'reserves' => false]));

    $this->get(route('royalty-tools.mechanicals.index'))->assertNotFound();
    $this->get(route('royalty-tools.ppd.index'))->assertNotFound();
    $this->get(route('royalty-tools.reserves.index'))->assertNotFound();
});

test('a run of a tool is listed among the data imports under the name of the tool', function () {
    $label = Label::factory()->create();
    $this->actingAs(labelAdmin($label));
    $import = DataImport::factory()->create(['label_id' => $label->id, 'importer' => 'ppd-tool']);

    $this->get(route('data-imports.index'))
        ->assertInertia(fn (Assert $page) => $page->where('imports.data.0.importer_name', 'PPD tool'));
    $this->get(route('data-imports.show', $import->id))
        ->assertInertia(fn (Assert $page) => $page->where('import.importer_name', 'PPD tool'));
});
