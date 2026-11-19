<?php

use App\Models\Label;

test('the help button opens the article for the page beside it, follows links inside the panel and survives a reload', function () {
    $this->actingAs(labelAdmin(Label::factory()->create(['name' => 'Example Records'])));

    $page = visit('/releases')
        ->assertMissing('[data-help="panel"]')
        ->click('[aria-label="Help for this page"]')
        ->assertSeeIn('[data-help="title"]', 'Releases')
        ->assertPathIs('/releases');

    $page->script('document.querySelector(\'[data-help="panel"] .handbook-prose a[href^="/handbook/"]\').click()');

    $page->assertPresent('[data-help="panel"] [aria-label="Back"]')
        ->assertPathIs('/releases')
        ->click('[data-help="panel"] [aria-label="Back"]')
        ->assertSeeIn('[data-help="title"]', 'Releases')
        ->click('[data-sidebar="menu-button"][href$="/tracks"]')
        ->assertPathIs('/tracks')
        ->assertSeeIn('[data-help="title"]', 'Releases')
        ->refresh()
        ->assertSeeIn('[data-help="title"]', 'Releases')
        ->click('[aria-label="Close handbook"]')
        ->assertMissing('[data-help="panel"]')
        ->assertNoJavaScriptErrors();
});

test('the handbook page lists the chapters and narrows them with the search', function () {
    $this->actingAs(labelAdmin(Label::factory()->create(['name' => 'Example Records'])));

    visit('/handbook')
        ->assertSee('Introduction')
        ->assertSee('Troubleshooting')
        ->fill('[aria-label="Search the handbook"]', 'libmp3lame')
        ->assertDontSeeIn('[aria-label="Handbook chapters"]', 'Contracts, payees and ledgers')
        ->assertSeeIn('[aria-label="Handbook chapters"]', 'Installation')
        ->assertNoJavaScriptErrors();
});
