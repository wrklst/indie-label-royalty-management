<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Updater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Which version the installation runs and whether it updates itself.
 * For the installation's own admins only, except resetCodeCache. The
 * update is done by the scheduler, never by the request that asks for it.
 */
class UpdatesController extends Controller
{
    private const string LATEST = 'updates:latest';

    public function edit(Request $request): Response
    {
        abort_unless($request->user()->installation_admin, 403);

        $obstacle = Updater::obstacle();
        $latest = $obstacle === null ? $this->latest() : ['version' => null, 'error' => null];

        return Inertia::render('settings/updates', [
            'current' => Updater::current(),
            'latest' => $latest['version'],
            'available' => $latest['version'] !== null && Updater::isBehind($latest['version']),
            'obstacle' => $obstacle ?? $latest['error'],
            'settings' => Updater::settings(),
            'serverTime' => now()->format('H:i'),
        ]);
    }

    /**
     * Whether new versions are installed without being asked for, and at which hour.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->installation_admin, 403);

        $validated = $request->validate(['auto' => ['required', 'boolean'], 'hour' => ['required', 'integer', 'between:0,23']]);

        Updater::remember(['auto' => (bool) $validated['auto'], 'hour' => (int) $validated['hour']]);

        return $this->saved($validated['auto'] ? __('New versions are installed automatically.') : __('New versions are installed when you ask for it.'));
    }

    /**
     * Ask the repository again which versions it has.
     */
    public function check(Request $request): RedirectResponse
    {
        abort_unless($request->user()->installation_admin, 403);

        Cache::forget(self::LATEST);

        return back();
    }

    /**
     * Have the scheduler install the newest version within the next minute.
     */
    public function install(Request $request): RedirectResponse
    {
        abort_unless($request->user()->installation_admin, 403);

        Cache::forget(self::LATEST);
        $obstacle = Updater::obstacle() ?? $this->latest()['error'];

        if ($obstacle !== null) {
            throw ValidationException::withMessages(['update' => $obstacle]);
        }

        Updater::remember(['requested' => true]);

        return $this->saved(__('The update starts within a minute. The site is in maintenance mode while it runs.'));
    }

    /**
     * Forget the PHP code this web server has compiled. Reached without
     * signing in, through an address signed by this installation: the
     * updater calls it after replacing the code. A server set up never
     * to look at its files again would go on running the old version.
     */
    public function resetCodeCache(): JsonResponse
    {
        return response()->json(['reset' => function_exists('opcache_reset') && opcache_reset()]);
    }

    /**
     * The newest version, asked for at most once an hour.
     *
     * @return array{version: ?string, error: ?string}
     */
    private function latest(): array
    {
        try {
            return ['version' => Cache::remember(self::LATEST, 3600, fn (): string => Updater::latest() ?? '') ?: null, 'error' => null];
        } catch (RuntimeException $exception) {
            return ['version' => null, 'error' => $exception->getMessage()];
        }
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
