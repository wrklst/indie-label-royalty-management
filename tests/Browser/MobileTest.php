<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\Payee;
use App\Models\PlayCount;
use App\Models\Statement;
use App\Models\User;
use App\Services\Royalties\StatementGenerator;

test('on a phone nothing runs off the screen: wide tables scroll inside their panels', function () {
    $scenario = royaltyScenario();
    $statement = app(StatementGenerator::class)->generate($scenario['period'])->sole();
    PlayCount::factory()->count(3)->create(['label_id' => $scenario['label']->id, 'platform' => 'spotify']);
    $this->actingAs($scenario['admin']);

    $pages = [
        '/dashboard?from=2026-01-01&to=2026-12-31', '/play-counts', "/periods/{$scenario['period']->id}", "/statements/{$statement->id}",
        "/releases/{$scenario['release']->id}", '/transactions', '/airplay', '/label-costs',
        '/profitability', '/statements', '/payouts', '/data-imports', '/catalog', '/settings/connections', '/settings/bandcamp', '/settings/label', '/settings/team', '/settings/profile', '/settings/security', '/settings/appearance',
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

test('on a phone the page of a payee of two labels does not run off the screen', function () {
    $scenario = royaltyScenario();
    app(StatementGenerator::class)->generate($scenario['period'])->sole()->update(['published_at' => now()]);
    $other = Label::factory()->create(['name' => 'Example Second Label with a rather long name', 'currency' => 'USD']);
    $otherPayee = Payee::factory()->create(['label_id' => $other->id]);
    Statement::factory()->create(['label_id' => $other->id, 'payee_id' => $otherPayee->id, 'published_at' => now()]);
    $user = User::factory()->create(['current_label_id' => $other->id]);
    $other->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $otherPayee->id]);
    $scenario['label']->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $scenario['payee']->id]);
    $this->actingAs($user);

    $offScreen = visit('/my-statements')->resize(390, 844)->script(<<<'JS'
        (() => {
            const width = document.documentElement.clientWidth;
            const scrolls = (el) => { for (let p = el.parentElement; p; p = p.parentElement) { if (['auto', 'scroll'].includes(getComputedStyle(p).overflowX)) return true; } return false; };

            return [...document.querySelectorAll('main *')]
                .filter((el) => el.getBoundingClientRect().width > 0 && el.getBoundingClientRect().right > width + 1 && !scrolls(el))
                .map((el) => el.tagName + ' ' + (el.textContent || '').trim().slice(0, 30)).slice(0, 5);
        })()
    JS);

    expect($offScreen)->toBe([]);
});
