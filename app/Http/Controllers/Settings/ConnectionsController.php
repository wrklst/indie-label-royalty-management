<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BandcampConnection;
use App\Models\Label;
use App\Services\Airplay\WebSearch;
use App\Services\Receipts\AiConnection;
use App\Support\CurrentLabel;
use App\Support\InstallationSettings;
use App\Support\WorkingCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The outside services a label works with, and for the installation's
 * own admins the file storage. Secrets are stored encrypted and never
 * sent back to the page; an empty secret field keeps the stored one.
 */
class ConnectionsController extends Controller
{
    public function edit(Request $request, CurrentLabel $currentLabel): Response
    {
        $label = $this->label($currentLabel);
        $bandcamp = BandcampConnection::query()->first();
        $storage = InstallationSettings::storage();

        return Inertia::render('settings/connections', [
            'search' => [
                'login' => $label->dataforseo_login,
                'password_set' => filled($label->dataforseo_password),
                'from_environment' => blank($label->dataforseo_login) && filled(config('services.dataforseo.login')),
            ],
            'ai' => [
                'provider' => $label->ai_provider,
                'key_set' => filled($label->ai_key),
                'endpoint' => $label->ai_endpoint,
                'model' => $label->ai_model,
                'services' => collect(AiConnection::SERVICES)->map(fn (array $service, string $key): array => ['value' => $key, ...$service])->values(),
            ],
            'bandcamp' => [
                'connected' => $bandcamp !== null,
                'enabled' => (bool) $bandcamp?->enabled,
                'last_sync_at' => $bandcamp?->last_sync_at?->toIso8601String(),
            ],
            'storage' => $request->user()->installation_admin ? [
                'disk' => WorkingCopy::disk(),
                'from_environment' => $storage === null,
                'bucket' => config('filesystems.disks.s3.bucket'),
                'region' => config('filesystems.disks.s3.region'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'path_style' => (bool) config('filesystems.disks.s3.use_path_style_endpoint'),
                'key_set' => filled(config('filesystems.disks.s3.key')),
            ] : null,
        ]);
    }

    /**
     * The DataForSEO login for the airplay research. An empty login
     * removes it; a changed one is proven against the service first.
     */
    public function search(Request $request, CurrentLabel $currentLabel, WebSearch $search): RedirectResponse
    {
        $label = $this->label($currentLabel);
        $validated = $request->validate([
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $login = $validated['login'] ?? null;
        $password = $validated['password'] ?? $label->dataforseo_password;

        if ($login === null) {
            $label->update(['dataforseo_login' => null, 'dataforseo_password' => null]);

            return $this->saved(__('Web search login removed.'));
        }

        if ($password === null) {
            throw ValidationException::withMessages(['password' => __('Enter the API password that belongs to this login.')]);
        }

        if ($login !== $label->dataforseo_login || $password !== $label->dataforseo_password) {
            try {
                $accepted = $search->accepts($login, $password);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['login' => $exception->getMessage()]);
            }

            if (! $accepted) {
                throw ValidationException::withMessages(['login' => __('DataForSEO does not accept this login and password.')]);
            }
        }

        $label->update(['dataforseo_login' => $login, 'dataforseo_password' => $password]);

        return $this->saved(__('Web search login saved.'));
    }

    /**
     * The AI service receipts are read with. No service removes it.
     */
    public function ai(Request $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $label = $this->label($currentLabel);
        $validated = $request->validate([
            'provider' => ['nullable', 'string', Rule::in(array_keys(AiConnection::SERVICES))],
            'key' => ['nullable', 'string', 'max:500'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
        ]);

        $service = $validated['provider'] ?? null;

        if ($service === null) {
            $label->update(['ai_provider' => null, 'ai_key' => null, 'ai_endpoint' => null, 'ai_model' => null]);

            return $this->saved(__('Receipt reading switched off.'));
        }

        // A key belongs to its service: switching the service asks for the new one.
        $key = $validated['key'] ?? ($service === $label->ai_provider ? $label->ai_key : null);
        $needsEndpoint = AiConnection::SERVICES[$service]['endpoint'] !== null;
        $endpoint = $needsEndpoint ? ($validated['endpoint'] ?? null) : null;
        $model = $validated['model'] ?? null;

        if ($key === null) {
            throw ValidationException::withMessages(['key' => __('Enter the API key of this service.')]);
        }

        if ($needsEndpoint && $endpoint === null) {
            throw ValidationException::withMessages(['endpoint' => __('This service needs its :field.', ['field' => AiConnection::SERVICES[$service]['endpoint']])]);
        }

        if ($needsEndpoint && $model === null) {
            throw ValidationException::withMessages(['model' => __('Name the model to read receipts with. It has to understand images.')]);
        }

        try {
            $models = AiConnection::models($service, $key, $endpoint);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['key' => $exception->getMessage()]);
        }

        // The list a service gives is a hint only: some list just part of what they serve.
        // What counts is whether the chosen model answers.
        try {
            AiConnection::prove($service, $key, $endpoint, $model);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['model' => __('The service did not answer with this model: :error', ['error' => $exception->getMessage()])
                .($models === null || $models === [] ? '' : ' '.__('Models it lists: :models', ['models' => implode(', ', $models)]))]);
        }

        $label->update(['ai_provider' => $service, 'ai_key' => $key, 'ai_endpoint' => $endpoint, 'ai_model' => $model]);

        return $this->saved(__('Receipt reading set up.'));
    }

    /**
     * Where the files of every label are stored. A bucket is proven to
     * take and return a file before it is used.
     */
    public function storage(Request $request): RedirectResponse
    {
        abort_unless($request->user()->installation_admin, 403);

        $validated = $request->validate([
            'disk' => ['required', Rule::in(['environment', 'local', 's3'])],
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'bucket' => ['nullable', 'required_if:disk,s3', 'string', 'max:255'],
            'endpoint' => ['nullable', 'url:https', 'max:255'],
            'path_style' => ['boolean'],
        ]);

        if ($validated['disk'] === 'environment') {
            InstallationSettings::putStorage(null);

            return $this->saved(__('File storage follows the environment file again.'));
        }

        if ($validated['disk'] === 'local') {
            InstallationSettings::putStorage(['disk' => 'local']);

            return $this->saved(__('New files are stored on this server.'));
        }

        $stored = InstallationSettings::storage() ?? [];
        $bucket = [
            'disk' => 's3',
            // Empty fields keep what is in use, whether set here before or in the environment.
            'key' => $validated['key'] ?? $stored['key'] ?? config('filesystems.disks.s3.key'),
            'secret' => $validated['secret'] ?? $stored['secret'] ?? config('filesystems.disks.s3.secret'),
            'region' => $validated['region'] ?? null,
            'bucket' => $validated['bucket'],
            'endpoint' => $validated['endpoint'] ?? null,
            'path_style' => $request->boolean('path_style'),
        ];

        if (blank($bucket['key']) || blank($bucket['secret'])) {
            throw ValidationException::withMessages(['key' => __('Enter the access key and the secret key of the bucket.')]);
        }

        try {
            InstallationSettings::probe($bucket);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['bucket' => $exception->getMessage()]);
        }

        InstallationSettings::putStorage($bucket);

        return $this->saved(__('New files are stored in the bucket. Files stored before stay where they are until you move them.'));
    }

    private function label(CurrentLabel $currentLabel): Label
    {
        return $currentLabel->get() ?? abort(404);
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
