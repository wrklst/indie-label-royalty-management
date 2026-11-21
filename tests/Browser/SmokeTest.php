<?php

use App\Enums\LabelRole;
use App\Models\ContractSplit;
use App\Models\Label;
use App\Models\Payee;
use App\Models\Statement;
use App\Models\User;
use App\Services\Royalties\PayoutService;
use App\Services\Royalties\StatementGenerator;
use Illuminate\Support\Facades\Storage;

test('a user can log in through the form and lands on the dashboard', function () {
    $user = labelAdmin(Label::factory()->create(['name' => 'Example Records']));

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->press('Log in')
        ->assertPathIs('/dashboard')
        ->assertSee('Example Records')
        ->assertSee('Nothing imported yet')
        ->assertNoJavaScriptErrors();
});

test('a new user is led through creating their first label', function () {
    $this->actingAs(User::factory()->create());

    visit('/dashboard')
        ->assertPathIs('/labels/create')
        ->assertSee('Set up your label')
        ->fill('name', 'Example Records')
        ->press('Create label')
        ->assertPathIs('/dashboard')
        ->assertSee('Example Records')
        ->assertSee('Royalties · EUR')
        ->assertNoJavaScriptErrors();
});

test('every page of a label with data renders without browser errors', function () {
    $scenario = royaltyScenario();
    $statement = app(StatementGenerator::class)->generate($scenario['period'])->sole();
    app(PayoutService::class)->record($scenario['period'], $scenario['payee'], '20', '2026-04-10', 'Bank transfer', $scenario['admin']);
    $this->actingAs($scenario['admin']);

    $pages = [
        '/dashboard?from=2026-01-01&to=2026-12-31' => 'Example Store',
        '/data-imports' => 'Example statement',
        '/data-imports/create' => 'Detect automatically',
        '/transactions' => 'Example Track',
        '/transactions/create?type=transfer' => 'New transfer',
        '/play-counts' => 'No play counts yet',
        '/periods' => '2026 Q1',
        "/periods/{$scenario['period']->id}" => 'Example Payee',
        "/periods/{$scenario['period']->id}/payees/{$scenario['payee']->id}" => 'Primary ledger',
        '/statements' => 'Example Payee',
        "/statements/{$statement->id}" => 'Download CSV',
        '/payouts' => 'Bank transfer',
        '/catalog' => 'Example Release',
        '/releases' => 'EX001',
        "/releases/{$scenario['release']->id}" => 'ZZTST2600001',
        "/releases/{$scenario['release']->id}/edit" => 'Save release',
        '/tracks' => 'Example Track',
        '/artists' => 'Example Artist',
        '/items' => '[Additionals]',
        '/contracts' => 'Example Deal',
        "/contracts/{$scenario['contract']->id}" => 'Releases that follow this contract',
        "/contracts/{$scenario['contract']->id}/edit" => 'Save contract',
        '/payees' => 'Example Payee',
        "/payees/{$scenario['payee']->id}" => 'Ledger balances',
        '/ledgers' => 'Only the primary ledger so far',
        '/my-statements' => 'My statements',
        '/settings/label' => 'Territories',
        '/settings/team' => 'Add a person',
    ];

    foreach ($pages as $url => $text) {
        visit($url)->assertSee($text)->assertNoSmoke();
    }
});

test('a payee only gets the statement portal and can read a published statement', function () {
    $scenario = royaltyScenario();
    app(StatementGenerator::class)->generate($scenario['period'])->sole()->update(['published_at' => now()]);
    $other = Payee::factory()->create(['label_id' => $scenario['label']->id, 'name' => 'Someone Else']);
    Statement::factory()->create(['label_id' => $scenario['label']->id, 'payee_id' => $other->id, 'period_id' => $scenario['period']->id, 'name' => 'Not mine', 'published_at' => now()]);
    $user = User::factory()->create(['current_label_id' => $scenario['label']->id]);
    $scenario['label']->users()->attach($user, ['role' => LabelRole::Payee->value, 'payee_id' => $scenario['payee']->id]);
    $this->actingAs($user);

    visit('/dashboard')
        ->assertPathIs('/my-statements')
        ->assertSee('My statements')
        ->assertDontSee('Transactions')
        ->assertDontSee('Not mine')
        ->click('2026 Q1')
        ->assertSee('Example Payee – 2026 Q1')
        ->assertSee('Still payable')
        ->assertDontSee('Publish to payee')
        ->click('[role="tab"]:has-text("Income")')
        ->assertSee('Example Track')
        ->assertSee('Streaming/Other')
        ->assertNoSmoke();
});

test('the settings opened from the profile list everything that belongs to the label', function () {
    $this->actingAs(labelAdmin(Label::factory()->create(['name' => 'Example Records'])));

    visit('/settings')
        ->assertPathIs('/settings/profile')
        ->assertSeeIn('nav[aria-label="Settings"]', 'Profile')
        ->assertSeeIn('nav[aria-label="Settings"]', 'Team')
        ->assertSeeIn('nav[aria-label="Settings"]', 'Connections')
        ->assertSeeIn('nav[aria-label="Settings"]', 'Bandcamp')
        ->click('nav[aria-label="Settings"] a[href$="/settings/connections"]')
        ->assertPathIs('/settings/connections')
        ->assertSee('Receipt reading')
        ->click('nav[aria-label="Settings"] a[href$="/settings/label"]')
        ->assertPathIs('/settings/label')
        ->assertSee('Accounting currency')
        ->assertNoJavaScriptErrors();
});

test('a period draws who the income goes to and a contract draws its splits', function () {
    $scenario = royaltyScenario();
    $this->actingAs($scenario['admin']);

    visit("/periods/{$scenario['period']->id}")
        ->assertSee('Where the money went')
        ->assertAttribute('section:has([data-money-flow]) [data-segmented-bar] [role="img"]', 'aria-label', 'Owed to payees 50%, Kept by the label 50%')
        ->assertNoJavaScriptErrors();

    visit('/contracts')
        ->assertAttribute('[data-segmented-bar] [role="img"]', 'aria-label', 'Example Payee 50%, Label 50%')
        ->assertNoJavaScriptErrors();
});

test('a signet dropped into the label settings is uploaded and shown in the sidebar', function () {
    Storage::fake('local');
    $signet = sys_get_temp_dir().'/example-signet.png';
    imagepng(imagecreatetruecolor(64, 64), $signet);
    $this->actingAs(labelAdmin(Label::factory()->create(['name' => 'Example Records'])));

    visit('/settings/label')
        ->assertMissing('[data-label-signet]')
        ->attach('[data-label-image="signet"] input[type="file"]', $signet)
        ->assertPresent('[data-label-signet]')
        ->assertPresent('[data-label-image="signet"] img[alt="Current signet"]')
        ->assertNoJavaScriptErrors();
});

test('every payee of a contract has its own colour and pointing at a name sets the other parts of the bar back', function () {
    $scenario = royaltyScenario();
    $scenario['contract']->splits()->first()->update(['split_amount' => '0.0625']);

    foreach (range(2, 11) as $number) {
        ContractSplit::factory()->create([
            'contract_id' => $scenario['contract']->id, 'split_amount' => '0.0625',
            'payee_id' => Payee::factory()->create(['label_id' => $scenario['label']->id, 'name' => "Payee {$number}"])->id,
        ]);
    }

    $this->actingAs($scenario['admin']);

    visit('/contracts')
        ->assertScript('(() => { const colour = (element) => getComputedStyle(element).backgroundColor; const bar = [...document.querySelectorAll("[data-segmented-bar] [data-part]")].map(colour); const dots = [...document.querySelectorAll("[data-split-dot]")].map(colour); const label = colour(document.querySelector("[data-label-dot]")); return dots.length === 11 && new Set(dots).size === 11 && dots.every((dot, index) => dot === bar[index]) && label === bar.at(-1) && ! dots.includes(label); })()')
        ->hover('[data-split-name="2"]')
        ->assertScript('(() => { const parts = [...document.querySelectorAll("[data-segmented-bar] [data-part]")]; return parts.filter((part) => part.classList.contains("opacity-25")).length === parts.length - 1 && ! parts[2].classList.contains("opacity-25"); })()')
        ->assertNoJavaScriptErrors();
});
