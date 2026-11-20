<?php

use App\Models\Label;
use App\Support\Updater;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * A repository that runs v1.2.0 and knows v1.10.0 as its newest version.
 * No program is really run; $answers replaces what one of them answers,
 * as text or as exit code.
 *
 * @param  array<string, string|int>  $answers
 */
function fakeRepository(array $answers = []): void
{
    $answers += [
        'git status*' => '',
        'git fetch*' => '',
        'git tag --list' => "v1.0.0\nv1.2.0\nv1.10.0\nv1.10.0beta\nv1.9.5-rc.2\nnightly\nv2-draft\n",
        'git describe*' => "v1.2.0\n",
        'git merge-base --is-ancestor v1.10.0 HEAD' => 1,
        'git merge-base --is-ancestor HEAD v1.10.0' => 0,
        'bash bin/update v1.10.0' => 0,
    ];

    Process::fake(function (PendingProcess $process) use ($answers) {
        $command = implode(' ', (array) $process->command);

        foreach ($answers as $pattern => $answer) {
            if (Str::is($pattern, $command)) {
                return is_int($answer) ? Process::result(exitCode: $answer) : Process::result($answer);
            }
        }

        throw new RuntimeException("Unexpected program: {$command}");
    });
}

function updateScriptRan(): bool
{
    try {
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['bash', 'bin/update', 'v1.10.0']);
    } catch (ExpectationFailedException) {
        return false;
    }

    return true;
}

beforeEach(function () {
    // Backups and the update log go under the storage path; keep them out of the real one.
    $this->storage = sys_get_temp_dir().'/updater-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->storage.'/logs');
    $this->app->useStoragePath($this->storage);
});

afterEach(fn () => File::deleteDirectory($this->storage));

test('the newest tagged version is installed and what happened is kept', function () {
    fakeRepository();

    $this->artisan('royalties:update --without-backup')->expectsOutputToContain('now runs v1.10.0')->assertSuccessful();

    expect(updateScriptRan())->toBeTrue()
        ->and(Updater::settings()['last'])->toMatchArray(['tag' => 'v1.10.0', 'from' => 'v1.2.0', 'status' => 'ok', 'backup' => null]);
});

test('the database is copied before the update', function () {
    fakeRepository();
    File::put($database = $this->storage.'/database.sqlite', 'the database');
    config(['database.connections.sqlite.database' => $database]);

    $this->artisan('royalties:update')->assertSuccessful();

    $backup = Updater::settings()['last']['backup'];

    expect($backup)->toStartWith($this->storage.'/app/update-backups/')->toEndWith('-before-v1.10.0.sqlite')
        ->and(File::get($backup))->toBe('the database');
});

test('without a backup of the database nothing is updated', function () {
    fakeRepository();
    config(['database.connections.sqlite.database' => ':memory:']);

    $this->artisan('royalties:update')->expectsOutputToContain('The database file could not be copied.')->assertFailed();

    expect(updateScriptRan())->toBeFalse();
});

test('an installation that has the newest version is left alone', function () {
    fakeRepository(['git merge-base --is-ancestor v1.10.0 HEAD' => 0]);

    $this->artisan('royalties:update --without-backup')->expectsOutputToContain('already runs v1.2.0')->assertSuccessful();

    expect(updateScriptRan())->toBeFalse();
});

test('checking only says which version is available', function () {
    fakeRepository();

    $this->artisan('royalties:update --check')->expectsOutputToContain('Version v1.10.0 is available')->assertSuccessful();

    expect(updateScriptRan())->toBeFalse();
});

test('an update is refused', function (array $answers, string $reason) {
    fakeRepository($answers);

    $this->artisan('royalties:update --without-backup')->expectsOutputToContain($reason)->assertFailed();

    expect(updateScriptRan())->toBeFalse();
})->with([
    'when files were changed on the server' => [['git status*' => " M routes/web.php\n M composer.json\n"], 'routes/web.php, composer.json'],
    'when the repository cannot be reached' => [['git fetch*' => 1], 'The repository could not be reached'],
    'when the installation has commits of its own' => [['git merge-base --is-ancestor HEAD v1.10.0' => 1], 'has commits that v1.10.0 does not contain'],
]);

test('a failed update is reported with what became of the site', function (int $exitCode, string $status, string $message) {
    fakeRepository(['bash bin/update v1.10.0' => $exitCode]);

    $this->artisan('royalties:update --without-backup')->expectsOutputToContain($message)->assertFailed();

    expect(Updater::settings()['last']['status'])->toBe($status);
})->with([
    'previous version restored' => [1, 'failed', 'The previous version runs again.'],
    'going back failed too' => [3, 'broken', 'The site is in maintenance mode.'],
]);

test('the scheduler does nothing unless an update was asked for or is due', function () {
    fakeRepository();
    Updater::remember(['auto' => true, 'hour' => 5]);
    $this->travelTo('2026-03-02 14:10:00');

    $this->artisan('royalties:update --scheduled --without-backup')->assertSuccessful();

    Process::assertNothingRan();
});

test('the scheduler installs an update that was asked for, once', function () {
    fakeRepository();
    Updater::remember(['requested' => true]);

    $this->artisan('royalties:update --scheduled --without-backup')->assertSuccessful();

    expect(updateScriptRan())->toBeTrue()
        ->and(Updater::settings()['requested'])->toBeFalse();
});

test('automatic updates run once a day at the chosen hour', function () {
    fakeRepository();
    Updater::remember(['auto' => true, 'hour' => 5]);
    $this->travelTo('2026-03-02 05:10:00');

    $this->artisan('royalties:update --scheduled --without-backup')->assertSuccessful();

    expect(updateScriptRan())->toBeTrue();

    $this->travelTo('2026-03-02 05:11:00');
    $this->artisan('royalties:update --scheduled --without-backup')->assertSuccessful();

    Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command === ['bash', 'bin/update', 'v1.10.0'], 1);
});

test('a version that failed is not tried again automatically, but on request', function () {
    fakeRepository();
    Updater::remember(['auto' => true, 'hour' => 5, 'last' => ['tag' => 'v1.10.0', 'from' => 'v1.2.0', 'status' => 'failed', 'at' => '2026-03-01T05:00:00+00:00', 'backup' => null]]);
    $this->travelTo('2026-03-02 05:10:00');

    $this->artisan('royalties:update --scheduled --without-backup')->expectsOutputToContain('failed before')->assertSuccessful();

    expect(updateScriptRan())->toBeFalse();

    Updater::remember(['requested' => true]);
    $this->artisan('royalties:update --scheduled --without-backup')->assertSuccessful();

    expect(updateScriptRan())->toBeTrue();
});

test('a refusal met by the scheduler is kept for the settings page', function () {
    fakeRepository(['git status*' => " M routes/web.php\n"]);
    Updater::remember(['requested' => true]);

    $this->artisan('royalties:update --scheduled')->assertFailed();

    expect(Updater::settings()['last'])->toMatchArray(['status' => 'refused'])
        ->and(Updater::settings()['last']['message'])->toContain('routes/web.php');
});

test('the settings page shows the version and takes the request and the schedule', function () {
    fakeRepository();
    $admin = labelAdmin(Label::factory()->create());
    $admin->forceFill(['installation_admin' => true])->save();
    $this->actingAs($admin);

    $this->get(route('updates.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/updates')
        ->where('current', 'v1.2.0')
        ->where('latest', 'v1.10.0')
        ->where('available', true)
        ->where('obstacle', null));

    $this->post(route('updates.install'))->assertSessionHasNoErrors();
    $this->put(route('updates.update'), ['auto' => '1', 'hour' => '13'])->assertSessionHasNoErrors();
    $this->put(route('updates.update'), ['auto' => '1', 'hour' => '24'])->assertSessionHasErrors('hour');

    expect(Updater::settings())->toMatchArray(['requested' => true, 'auto' => true, 'hour' => 13])
        ->and(updateScriptRan())->toBeFalse();
});

test('an update that cannot run is not taken as a request', function () {
    fakeRepository(['git status*' => " M routes/web.php\n"]);
    $admin = labelAdmin(Label::factory()->create());
    $admin->forceFill(['installation_admin' => true])->save();

    $this->actingAs($admin)->post(route('updates.install'))->assertSessionHasErrors('update');

    expect(Updater::settings()['requested'])->toBeFalse();
});

test('updates are for admins of the installation only', function (string $method, string $route) {
    fakeRepository();

    $this->actingAs(labelAdmin(Label::factory()->create()))->{$method}(route($route), ['auto' => '1', 'hour' => '5'])->assertForbidden();

    expect(Updater::settings())->toMatchArray(['requested' => false, 'auto' => false]);
})->with([['get', 'updates.edit'], ['put', 'updates.update'], ['post', 'updates.check'], ['post', 'updates.install']]);
