<?php

use App\Models\Artist;
use App\Models\Label;
use App\Models\Track;

test('artists named as remixer in a track title are credited once, within their own label', function () {
    $label = Label::factory()->create();
    $main = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Example Artist']);
    $remixer = Artist::factory()->create(['label_id' => $label->id, 'name' => 'Sample Ensemble']);
    $elsewhere = Artist::factory()->create(['label_id' => Label::factory()->create()->id, 'name' => 'Other Label Act']);
    $track = fn (string $name): Track => Track::factory()->create(['label_id' => $label->id, 'artist_id' => $main->id, 'name' => $name]);

    $dashed = $track('Opening - Sample Ensemble Remix');
    $bracketed = $track('Second Track (sample ensemble Rework)');
    $unknown = $track('Opening (Big Mix)');
    $foreign = $track('Opening (Other Label Act Remix)');
    $plain = $track('Sample Ensemble');

    $this->artisan('royalties:credit-remixers')->expectsOutputToContain('2 remixer credits added.')->assertSuccessful();
    $this->artisan('royalties:credit-remixers')->expectsOutputToContain('0 remixer credits added.')->assertSuccessful();

    expect($dashed->artists()->get()->map(fn (Artist $artist): array => [$artist->id, $artist->pivot->role])->all())->toBe([[$remixer->id, 'remixer']])
        ->and($bracketed->artists()->pluck('artists.id')->all())->toBe([$remixer->id])
        ->and($unknown->artists()->count())->toBe(0)
        ->and($foreign->artists()->count())->toBe(0)
        ->and($plain->artists()->count())->toBe(0)
        ->and($elsewhere->tracks()->count())->toBe(0);
});
