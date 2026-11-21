<?php

use App\Models\PlayCount;
use App\Services\Royalties\StatementGenerator;

test('on a phone nothing runs off the screen: wide tables scroll inside their panels', function () {
    $scenario = royaltyScenario();
    $statement = app(StatementGenerator::class)->generate($scenario['period'])->sole();
    PlayCount::factory()->count(3)->create(['label_id' => $scenario['label']->id, 'platform' => 'spotify']);
    $this->actingAs($scenario['admin']);

    $pages = [
        '/dashboard?from=2026-01-01&to=2026-12-31', '/play-counts', "/periods/{$scenario['period']->id}", "/statements/{$statement->id}",
        "/releases/{$scenario['release']->id}", '/transactions', '/airplay', '/label-costs',
        '/profitability', '/statements', '/payouts', '/data-imports', '/catalog', '/settings/connections', '/settings/bandcamp', '/settings/label',
    ];

    foreach ($pages as $url) {
        // Everything in the page that ends right of the screen and is not inside something that scrolls sideways.
        $offScreen = visit($url)->resize(390, 844)->script(<<<'JS'
            (() => {
                const width = document.documentElement.clientWidth;
                const scrolls = (el) => { for (let p = el.parentElement; p; p = p.parentElement) { if (['auto', 'scroll'].includes(getComputedStyle(p).overflowX)) return true; } return false; };

                return [...document.querySelectorAll('main *')]
                    .filter((el) => el.getBoundingClientRect().width > 0 && el.getBoundingClientRect().right > width + 1 && !scrolls(el))
                    .map((el) => el.tagName + ' ' + (el.textContent || '').trim().slice(0, 30)).slice(0, 5);
            })()
        JS);

        expect($offScreen)->toBe([], $url);
    }
});
