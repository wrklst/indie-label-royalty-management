<?php

use App\Models\Label;
use Illuminate\Support\Facades\Storage;

test('a statement can be imported from the command line', function () {
    Storage::fake('local');
    $label = Label::factory()->create(['slug' => 'example']);

    $this->artisan('royalties:import', [
        'file' => base_path('tests/Fixtures/zebralution_2026q3_sample.csv'),
        '--label' => 'example',
        '--paid' => '2026-10-15',
    ])->assertSuccessful();

    $this->assertDatabaseHas('data_imports', ['label_id' => $label->id, 'status' => 'imported', 'transactions_count' => 67]);
});

test('the command refuses a zebralution file without a paid date', function () {
    Storage::fake('local');
    Label::factory()->create(['slug' => 'example']);

    $this->artisan('royalties:import', ['file' => base_path('tests/Fixtures/zebralution_2026q3_sample.csv'), '--label' => 'example'])
        ->assertFailed();

    $this->assertDatabaseCount('data_imports', 0);
});
