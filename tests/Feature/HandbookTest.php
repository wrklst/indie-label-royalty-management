<?php

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\User;
use App\Services\Handbook\Handbook;
use Inertia\Testing\AssertableInertia as Assert;

test('an article takes its title from the first heading and its chapter and order from the file name', function () {
    expect(Handbook::parse('23-airplay', "# Airplay\n\nWhere your tracks were played.\n\n## Links\n"))->toBe([
        'slug' => '23-airplay', 'title' => 'Airplay', 'chapter' => 'Catalog', 'order' => 23,
        'body' => "Where your tracks were played.\n\n## Links",
    ]);
});

test('links between articles point at the handbook and raw HTML is escaped', function () {
    $html = app(Handbook::class)->html(Handbook::parse('01-example', "# Example\n\nSee [Airplay](23-airplay.md#links) and <script>alert(1)</script>.\n\n## Good to know\n"));

    expect($html)->toContain('href="/handbook/23-airplay#links"')
        ->toContain('<h2 id="good-to-know">Good to know</h2>')
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

test('every link between articles and every page with a help button leads to an article', function () {
    $articles = app(Handbook::class)->articles();
    preg_match_all("/\\['[^']+', '([\\w-]+)'\\]|DEFAULT_TOPIC = '([\\w-]+)'/", file_get_contents(resource_path('js/components/help/help-store.ts')), $topics);
    $linked = $articles->flatMap(function (array $article): array {
        preg_match_all('/\]\(([^)#\s]+\.md)(?:#[\w-]*)?\)/', $article['body'], $links);

        return array_map(fn (string $file): string => basename($file, '.md'), $links[1]);
    });

    expect($articles)->not->toBeEmpty()
        ->and(array_filter([...$topics[1], ...$topics[2]]))->not->toBeEmpty()->each->toBeIn($articles->keys()->all())
        ->and($linked->unique()->values()->all())->each->toBeIn($articles->keys()->all());
});

test('the readme only links to articles that exist', function () {
    preg_match_all('/\]\(docs\/([\w-]+)\.md\)/', file_get_contents(base_path('README.md')), $links);

    expect($links[1])->not->toBeEmpty()->each->toBeIn(app(Handbook::class)->articles()->keys()->all());
});

test('the handbook shows the first article by default, any article by its name and all chapters in order', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()));

    $this->get(route('handbook'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('handbook/index')
        ->where('article.slug', '01-introduction')
        ->where('chapters.0.title', 'Setup')
        ->where('chapters.0.articles.0.slug', '01-introduction'));

    $this->get(route('handbook', '23-airplay'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('article.title', 'Airplay')
        ->where('article.chapter', 'Catalog')
        ->where('article.html', fn (string $html): bool => str_contains($html, '<h2 id="')));

    $this->get(route('handbook', 'no-such-article'))->assertNotFound();
});

test('the help panel gets one article as JSON', function () {
    $this->actingAs(labelAdmin(Label::factory()->create()));

    $this->getJson(route('handbook.panel', '23-airplay'))
        ->assertOk()
        ->assertJson(['slug' => '23-airplay', 'title' => 'Airplay', 'chapter' => 'Catalog'])
        ->assertJsonStructure(['html']);

    $this->getJson(route('handbook.panel', 'no-such-article'))->assertNotFound();
});

test('the handbook is for label admins only', function () {
    $this->get(route('handbook'))->assertRedirect(route('login'));

    $label = Label::factory()->create();
    $payee = User::factory()->create();
    $label->users()->attach($payee, ['role' => LabelRole::Payee->value]);

    $this->actingAs($payee)->get(route('handbook'))->assertRedirect(route('portal.index'));
    $this->actingAs($payee)->get(route('handbook.panel', '23-airplay'))->assertRedirect(route('portal.index'));
});
