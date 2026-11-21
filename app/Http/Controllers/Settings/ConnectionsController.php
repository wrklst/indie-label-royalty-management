<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\AirplayMarketsRequest;
use App\Http\Requests\ServiceSettingsRequest;
use App\Models\BandcampConnection;
use App\Models\Label;
use App\Services\Airplay\WebSearch;
use App\Services\Receipts\AiConnection;
use App\Services\Receipts\ModelCatalogue;
use App\Services\Receipts\SampleReceipt;
use App\Services\Releases\ZebralutionSheet;
use App\Support\CurrentLabel;
use App\Support\DirectUploads;
use App\Support\InstallationSettings;
use App\Support\PublicAddress;
use App\Support\Services;
use App\Support\WorkingCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Everything a label connects or switches on: its services, the web
 * search, the AI service, and for the installation's own admins the
 * file storage. Secrets are stored encrypted and never
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
            // Each service with its switch; the options of the ones that have any follow below.
            'services' => collect(Services::ALL)->map(fn (array $service, string $key): array => [
                'key' => $key,
                'name' => $service['name'],
                'kind' => $service['kind'],
                'purpose' => $service['purpose'],
                'gates' => $service['gates'],
                'enabled' => $label->usesService($key),
            ])->values(),
            'zebralution' => [
                'number' => $label->deliverySetting('zebralution.number'),
                'origin_country' => $label->deliverySetting('zebralution.origin_country'),
                'price_group' => (int) $label->deliverySetting('zebralution.price_group', 20),
                'work_rights' => $label->deliverySetting('zebralution.work_rights', ZebralutionSheet::WORK_RIGHTS[0]),
                'work_rights_options' => ZebralutionSheet::WORK_RIGHTS,
            ],
            // A stored connection is shown as what it is, with secrets cut down to a hint.
            'search' => [
                'airplay_markets' => $label->airplayMarkets(),
                'connected' => filled($label->dataforseo_login),
                'login' => filled($label->dataforseo_login) ? Str::mask((string) $label->dataforseo_login, '•', 3, max(0, mb_strlen((string) $label->dataforseo_login) - 6)) : null,
                'from_environment' => blank($label->dataforseo_login) && filled(config('services.dataforseo.login')),
            ],
            'ai' => [
                'connected' => AiConnection::isConfigured($label),
                'provider' => $label->ai_provider,
                'service' => AiConnection::SERVICES[$label->ai_provider ?? '']['name'] ?? null,
                'key_hint' => filled($label->ai_key) ? '••••'.mb_substr((string) $label->ai_key, -4) : null,
                'endpoint_label' => AiConnection::SERVICES[$label->ai_provider ?? '']['endpoint'] ?? null,
                'endpoint' => $label->ai_endpoint,
                'model' => AiConnection::model($label),
                'trial' => $label->ai_trial,
                'services' => collect(AiConnection::SERVICES)->map(fn (array $service, string $key): array => ['value' => $key, ...$service])->values(),
                // Models that take pictures, with prices, for the services the public catalogue knows.
                'catalogue' => ModelCatalogue::all(),
            ],
            'bandcamp' => [
                'connected' => $bandcamp !== null,
                'enabled' => (bool) $bandcamp?->enabled,
                'last_sync_at' => $bandcamp?->last_sync_at?->toIso8601String(),
                'disable_fee' => (bool) $label->importerSetting('bandcamp.disable_fee', false),
                'exclude_physical' => (bool) $label->importerSetting('bandcamp.exclude_physical', false),
            ],
            'storage' => $request->user()->installation_admin ? [
                'disk' => WorkingCopy::disk(),
                'from_environment' => $storage === null,
                'bucket' => config('filesystems.disks.s3.bucket'),
                'region' => config('filesystems.disks.s3.region'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'key_hint' => filled(config('filesystems.disks.s3.key')) ? '••••'.mb_substr((string) config('filesystems.disks.s3.key'), -4) : null,
                // Whether browsers at this address may send files to the bucket themselves.
                'direct_uploads' => WorkingCopy::isLocal(WorkingCopy::disk()) ? null : DirectUploads::allowed($request->getSchemeAndHttpHost()),
                'origin' => $request->getSchemeAndHttpHost(),
            ] : null,
        ]);
    }

    /**
     * The switch and the options of one service. A card that does not
     * send a field leaves it alone, and no card touches another one's.
     */
    public function service(ServiceSettingsRequest $request, CurrentLabel $currentLabel, string $service): RedirectResponse
    {
        abort_unless(Services::has($service), 404);

        $label = $this->label($currentLabel);
        $delivery = $label->delivery_settings ?? [];
        $importer = $label->importer_settings ?? [];

        if ($request->has('enabled')) {
            $delivery['distributors'][$service] = $request->boolean('enabled');
        }

        if ($service === Services::ZEBRALUTION && $request->has('number')) {
            $delivery['zebralution'] = [
                'number' => $request->validated('number'),
                'origin_country' => $request->validated('origin_country'),
                'price_group' => (int) ($request->validated('price_group') ?? 20),
                'work_rights' => $request->validated('work_rights') ?? ZebralutionSheet::WORK_RIGHTS[0],
            ];
        }

        foreach ($service === Services::BANDCAMP ? ['disable_fee', 'exclude_physical'] : [] as $option) {
            if ($request->has($option)) {
                $importer['bandcamp'][$option] = $request->boolean($option);
            }
        }

        $label->update(['delivery_settings' => $delivery ?: null, 'importer_settings' => $importer ?: null]);

        return $this->saved(__(':service saved.', ['service' => Services::ALL[$service]['name']]));
    }

    /**
     * The countries the airplay research searches in; the web search card holds them.
     */
    public function markets(AirplayMarketsRequest $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $this->label($currentLabel)->update(['airplay_markets' => $request->validated('airplay_markets')]);

        return $this->saved(__('Airplay research countries saved.'));
    }

    /**
     * Connect the web search for the airplay research. The login is
     * always proven against DataForSEO before it is stored.
     */
    public function search(Request $request, CurrentLabel $currentLabel, WebSearch $search): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        try {
            $accepted = $search->accepts($validated['login'], $validated['password']);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['login' => $exception->getMessage()]);
        }

        if (! $accepted) {
            throw ValidationException::withMessages(['login' => __('DataForSEO does not accept this login and password.')]);
        }

        $this->label($currentLabel)->update(['dataforseo_login' => $validated['login'], 'dataforseo_password' => $validated['password']]);

        return $this->saved(__('Web search connected. DataForSEO accepted the login.'));
    }

    /**
     * Connect the AI service receipts are read with. The chosen model is
     * always tried on a sample receipt before the connection is stored.
     */
    public function ai(Request $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $label = $this->label($currentLabel);
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys(AiConnection::SERVICES))],
            'key' => ['nullable', 'string', 'max:500'],
            'endpoint' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
        ]);

        $service = $validated['provider'];

        $key = $validated['key'] ?? null;
        $needsEndpoint = AiConnection::SERVICES[$service]['endpoint'] !== null;
        $endpoint = $needsEndpoint ? ($validated['endpoint'] ?? null) : null;
        $model = $validated['model'] ?? null;

        if ($key === null) {
            throw ValidationException::withMessages(['key' => __('Enter the API key of this service.')]);
        }

        if ($needsEndpoint && $endpoint === null) {
            throw ValidationException::withMessages(['endpoint' => __('This service needs its :field.', ['field' => AiConnection::SERVICES[$service]['endpoint']])]);
        }

        // Only who runs the installation may point it at its own machine or network, for a model
        // hosted there. From anyone else such an address would be a way to look around inside.
        if ($service === 'openai-compatible' && $endpoint !== null && ! $request->user()->installation_admin && ! (str_starts_with(strtolower($endpoint), 'https://') && PublicAddress::is($endpoint))) {
            throw ValidationException::withMessages(['endpoint' => __('Enter an https address on the internet. An address on this server or its network can only be set by the person who runs the installation.')]);
        }

        if ($needsEndpoint && $model === null) {
            throw ValidationException::withMessages(['model' => __('Name the model to read receipts with. It has to understand images.')]);
        }

        try {
            $models = AiConnection::models($service, $key, $endpoint);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['key' => $exception->getMessage()]);
        }

        // The list a service gives is a hint only: some list just part of what they serve. What
        // counts is whether the chosen model reads a receipt, so it is given an invented one.
        try {
            $trial = SampleReceipt::isAvailable() ? AiConnection::trial($service, $key, $endpoint, $model) : null;

            if ($trial === null) {
                AiConnection::prove($service, $key, $endpoint, $model);
            }
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['model' => __('This model could not read the sample receipt: :error', ['error' => $exception->getMessage()])
                .($models === null || $models === [] ? '' : ' '.__('Models the service lists: :models', ['models' => implode(', ', $models)]))]);
        }

        $label->update([
            'ai_provider' => $service, 'ai_key' => $key, 'ai_endpoint' => $endpoint, 'ai_model' => $model,
            'ai_trial' => $trial === null ? null : [...$trial, 'at' => now()->toIso8601String()],
        ]);

        return $this->saved(match (true) {
            $trial === null => __('Receipt reading set up.'),
            $trial['missed'] === [] => __('Receipt reading set up. The model read the sample receipt correctly in :seconds seconds.', ['seconds' => $trial['seconds']]),
            default => __('Receipt reading set up, but the model got :fields wrong on the sample receipt (:seconds seconds). Another model may read better.', [
                'fields' => implode(', ', $trial['missed']), 'seconds' => $trial['seconds'],
            ]),
        });
    }

    /**
     * Switch the model of the connected AI service. The key stays; the
     * new model is tried on the sample receipt before it replaces the old one.
     */
    public function aiModel(Request $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $label = $this->label($currentLabel);
        abort_unless(AiConnection::isConfigured($label), 404);

        $model = $request->validate(['model' => ['required', 'string', 'max:255']])['model'];

        try {
            $trial = SampleReceipt::isAvailable() ? AiConnection::trial((string) $label->ai_provider, (string) $label->ai_key, $label->ai_endpoint, $model) : null;

            if ($trial === null) {
                AiConnection::prove((string) $label->ai_provider, (string) $label->ai_key, $label->ai_endpoint, $model);
            }
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['model' => __('This model could not read the sample receipt: :error', ['error' => $exception->getMessage()])]);
        }

        $label->update(['ai_model' => $model, 'ai_trial' => $trial === null ? null : [...$trial, 'at' => now()->toIso8601String()]]);

        return $this->saved(match (true) {
            $trial === null => __('Model changed.'),
            $trial['missed'] === [] => __('Model changed. It read the sample receipt correctly in :seconds seconds.', ['seconds' => $trial['seconds']]),
            default => __('Model changed, but it got :fields wrong on the sample receipt (:seconds seconds).', ['fields' => implode(', ', $trial['missed']), 'seconds' => $trial['seconds']]),
        });
    }

    /**
     * Where the files of every label are stored. A bucket is proven to
     * take and return a file before it is used.
     */
    public function storage(Request $request): RedirectResponse
    {
        abort_unless($request->user()->installation_admin, 403);

        $validated = $request->validate([
            'disk' => ['required', Rule::in(['local', 's3'])],
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'bucket' => ['nullable', 'required_if:disk,s3', 'string', 'max:255'],
            'endpoint' => ['nullable', 'url:https', 'max:255'],
            'path_style' => ['boolean'],
        ]);

        if ($validated['disk'] === 'local') {
            InstallationSettings::putStorage(['disk' => 'local']);

            return $this->saved(__('New files are stored on this server.'));
        }

        $bucket = [
            'disk' => 's3',
            'key' => $validated['key'] ?? null,
            'secret' => $validated['secret'] ?? null,
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

    /**
     * Have the bucket take uploads straight from browsers at this address.
     */
    public function directUploads(Request $request): RedirectResponse
    {
        abort_unless($request->user()->installation_admin, 403);

        try {
            DirectUploads::allow($request->getSchemeAndHttpHost());
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['direct_uploads' => __('The bucket did not take the setting: :answer', ['answer' => mb_substr($exception->getMessage(), 0, 300)])]);
        }

        return $this->saved(__('Uploads now go straight to the bucket.'));
    }

    /**
     * Disconnect the web search. An installation-wide login in the environment stays in use.
     */
    public function destroySearch(CurrentLabel $currentLabel): RedirectResponse
    {
        $this->label($currentLabel)->update(['dataforseo_login' => null, 'dataforseo_password' => null]);

        return $this->saved(__('Web search disconnected.'));
    }

    /**
     * Disconnect the AI service. Receipts are stored unread from then on.
     */
    public function destroyAi(CurrentLabel $currentLabel): RedirectResponse
    {
        $this->label($currentLabel)->update(['ai_provider' => null, 'ai_key' => null, 'ai_endpoint' => null, 'ai_model' => null, 'ai_trial' => null]);

        return $this->saved(__('Receipt reading disconnected.'));
    }

    /**
     * Forget the file storage set here. The environment file decides again.
     */
    public function destroyStorage(Request $request): RedirectResponse
    {
        abort_unless($request->user()->installation_admin, 403);

        InstallationSettings::putStorage(null);

        return $this->saved(__('File storage follows the environment file again.'));
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
