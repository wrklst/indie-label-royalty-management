<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * A repository at v1.0.0 that also knows v1.1.0, with bin/update in it
 * and stand-ins for php, composer and npm that write down how they were
 * called. A stand-in fails when its call contains the text in FAIL_ON:
 * the first time only, or every time with FAIL_ALWAYS.
 */
beforeEach(function () {
    $this->repository = sys_get_temp_dir().'/update-script-'.bin2hex(random_bytes(6));
    $this->programs = $this->repository.'-programs';
    File::ensureDirectoryExists($this->repository.'/bin');
    File::ensureDirectoryExists($this->programs);
    File::copy(base_path('bin/update'), $this->repository.'/bin/update');

    foreach (['php', 'composer', 'npm'] as $program) {
        File::put($this->programs.'/'.$program, "#!/bin/sh\necho \"{$program} \$*\" >> \"\$CALLS\"\ncase \"{$program} \$*\" in *\"\${FAIL_ON:-never}\"*) { [ -n \"\$FAIL_ALWAYS\" ] || [ ! -e \"\$CALLS.failed\" ]; } && { touch \"\$CALLS.failed\"; exit 1; };; esac\nexit 0\n");
        chmod($this->programs.'/'.$program, 0755);
    }

    $git = fn (string ...$arguments) => Process::path($this->repository)
        ->run(['git', '-c', 'user.name=Example', '-c', 'user.email=example@example.com', '-c', 'commit.gpgsign=false', '-c', 'tag.gpgsign=false', ...$arguments])->throw();

    $git('init', '--quiet');
    $git('add', '.');
    $git('commit', '--quiet', '-m', 'First version');
    $git('tag', 'v1.0.0');
    File::put($this->repository.'/new-file', 'new');
    $git('add', '.');
    $git('commit', '--quiet', '-m', 'Second version');
    $git('tag', 'v1.1.0');
    $git('reset', '--quiet', '--hard', 'v1.0.0');

    $this->update = fn (string $failOn = 'never', bool $always = false) => Process::path($this->repository)->env([
        'PATH' => $this->programs.':'.getenv('PATH'), 'UPDATE_PHP' => $this->programs.'/php',
        'CALLS' => $this->repository.'-calls', 'FAIL_ON' => $failOn, 'FAIL_ALWAYS' => $always ? '1' : '',
    ])->run(['bash', 'bin/update', 'v1.1.0']);
    $this->calls = fn (): string => File::get($this->repository.'-calls');
    $this->version = fn (): string => trim(Process::path($this->repository)->run(['git', 'describe', '--tags'])->output());
});

afterEach(function () {
    File::deleteDirectory($this->repository);
    File::deleteDirectory($this->programs);
    File::delete([$this->repository.'-calls', $this->repository.'-calls.failed']);
});

test('the script moves to the tag, installs, migrates and opens the site again', function () {
    $result = ($this->update)();

    expect($result->exitCode())->toBe(0)
        ->and(($this->version)())->toBe('v1.1.0')
        ->and(($this->calls)())->toBe(implode("\n", [
            'php artisan down --retry=60',
            'php artisan optimize:clear',
            'composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev',
            'npm ci',
            'npm run build',
            'php artisan migrate --force',
            'php artisan optimize',
            'php artisan royalties:reset-code-cache',
            'php artisan horizon:terminate',
            'php artisan up',
        ])."\n");
});

test('when a step fails the previous version is put back and the site opens again', function (string $failOn) {
    $result = ($this->update)($failOn);

    expect($result->exitCode())->toBe(1)
        ->and(($this->version)())->toBe('v1.0.0')
        ->and(file_exists($this->repository.'/new-file'))->toBeFalse()
        ->and(($this->calls)())->toEndWith("php artisan up\n");
})->with(['the migration' => 'migrate', 'the build' => 'run build']);

test('when going back fails as well the site stays in maintenance mode', function () {
    $result = ($this->update)('composer install', always: true);

    expect($result->exitCode())->toBe(3)
        ->and($result->output())->toContain('The site stays in maintenance mode')
        ->and(($this->calls)())->not->toContain('php artisan up');
});
