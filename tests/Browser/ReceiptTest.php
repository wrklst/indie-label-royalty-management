<?php

use App\Models\Label;
use App\Models\Receipt;
use Illuminate\Support\Facades\Cache;
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

test('the connections page offers the models of a service with their prices and shows a stored connection as a card', function () {
    Cache::put('receipt-models', ['infomaniak' => [
        ['id' => 'example/small-vision', 'name' => 'Small Vision', 'description' => 'Example description', 'input_price' => 0.25, 'output_price' => 0.5, 'per_hundred' => 0.08, 'fixed_format' => true, 'reasoning' => false, 'released' => '2026-03-16'],
        ['id' => 'example/large-vision', 'name' => 'Large Vision', 'description' => 'Another description', 'input_price' => 1.0, 'output_price' => 4.0, 'per_hundred' => 0.36, 'fixed_format' => false, 'reasoning' => true, 'released' => '2026-03-16'],
    ]]);
    $label = Label::factory()->create(['name' => 'Example Records']);
    $this->actingAs(labelAdmin($label));

    visit('/settings/connections')
        ->assertSee('Receipt reading')
        ->assertSee('Not connected')
        ->assertDontSee('File storage')
        ->select('#ai_provider', 'infomaniak')
        ->assertSee('Product ID')
        ->assertSeeIn('[data-model-details]', 'Small Vision')
        ->assertSeeIn('[data-model-details]', 'about $0.08 per 100 receipts')
        ->select('#ai_model', 'example/large-vision')
        ->assertSeeIn('[data-model-details]', 'slower, more careful')
        ->select('#ai_model', 'other')
        ->assertPresent('#ai_model_other')
        ->select('#ai_provider', 'anthropic')
        ->assertDontSee('Product ID')
        ->assertNoJavaScriptErrors();

    $label->update(['ai_provider' => 'infomaniak', 'ai_key' => 'example-key-9f3a', 'ai_endpoint' => '12345', 'ai_model' => 'example/small-vision']);

    visit('/settings/connections')
        ->assertSeeIn('[data-connection="Infomaniak AI Tools"]', 'example/small-vision')
        ->assertSeeIn('[data-connection="Infomaniak AI Tools"]', '9f3a')
        ->assertDontSee('example-key-9f3a')
        ->assertMissing('#ai_key')
        ->assertPresent('[aria-label="Disconnect Infomaniak AI Tools"]')
        ->assertNoJavaScriptErrors();
});
