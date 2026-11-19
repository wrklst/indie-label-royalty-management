<?php

use App\Models\Label;
use App\Models\Receipt;
use Illuminate\Support\Facades\Storage;

test('a receipt is uploaded on the label costs page and booked as a cost', function () {
    Storage::fake('local');
    $label = Label::factory()->create(['name' => 'Example Records', 'currency' => 'EUR']);
    $file = sys_get_temp_dir().'/till-receipt.png';
    imagepng(imagecreatetruecolor(20, 20), $file);
    $this->actingAs(labelAdmin($label));

    visit('/label-costs')
        ->attach('#receipt_files', $file)
        ->press('Upload')
        ->assertSee('Receipt uploaded.')
        ->assertSee('not read')
        ->press('Book as cost')
        ->fill('#amount', '49.90')
        ->press('Book cost')
        ->assertSee('Cost booked.')
        ->assertPresent('[title="Open receipt"]')
        ->assertNoJavaScriptErrors();

    expect(Receipt::query()->sole()->transaction_id)->not->toBeNull();
});

test('the connections page offers the services and asks for what the chosen one needs', function () {
    $this->actingAs(labelAdmin(Label::factory()->create(['name' => 'Example Records'])));

    visit('/settings/connections')
        ->assertSee('Web search')
        ->assertSee('Receipt reading')
        ->assertSee('Not connected')
        ->assertDontSee('File storage')
        ->select('#ai_provider', 'infomaniak')
        ->assertSee('Product ID')
        ->select('#ai_provider', 'anthropic')
        ->assertDontSee('Product ID')
        ->assertNoJavaScriptErrors();
});
