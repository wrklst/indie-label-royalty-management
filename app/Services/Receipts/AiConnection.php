<?php

namespace App\Services\Receipts;

use App\Models\Label;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;
use Laravel\Ai\Providers\Provider;
use RuntimeException;
use Throwable;

/**
 * The AI service a label chose for reading receipts, as a provider of
 * the Laravel AI SDK. Adding a service means adding it to SERVICES and
 * to configuration(); everything else goes through the SDK.
 */
class AiConnection
{
    /**
     * The services on offer, by the name stored with the label.
     *
     * @var array<string, array{name: string, endpoint: string|null, model: string|null}>
     */
    public const array SERVICES = [
        'infomaniak' => ['name' => 'Infomaniak AI Tools', 'endpoint' => 'Product ID', 'model' => null],
        'openai' => ['name' => 'OpenAI', 'endpoint' => null, 'model' => 'gpt-5.4-mini'],
        'anthropic' => ['name' => 'Anthropic', 'endpoint' => null, 'model' => 'claude-haiku-5-5'],
        'mistral' => ['name' => 'Mistral', 'endpoint' => null, 'model' => 'mistral-small-latest'],
        'gemini' => ['name' => 'Google Gemini', 'endpoint' => null, 'model' => 'gemini-3-flash'],
        'openai-compatible' => ['name' => 'Other OpenAI-compatible service', 'endpoint' => 'Base URL', 'model' => null],
    ];

    public static function isConfigured(Label $label): bool
    {
        return self::configuration($label->ai_provider, $label->ai_key, $label->ai_endpoint, $label->ai_model) !== null;
    }

    /**
     * @throws RuntimeException when the label has no complete connection
     */
    public static function provider(Label $label): Provider
    {
        $configuration = self::configuration($label->ai_provider, $label->ai_key, $label->ai_endpoint, $label->ai_model)
            ?? throw new RuntimeException('Receipt reading is not set up. Choose an AI service under Settings → Connections.');

        return Ai::build([...$configuration, 'name' => 'label_'.$label->id.'_'.md5(json_encode($configuration, JSON_THROW_ON_ERROR))]);
    }

    /**
     * The model to read with: the label's choice, else the service's default.
     */
    public static function model(Label $label): ?string
    {
        return $label->ai_model ?: (self::SERVICES[$label->ai_provider ?? '']['model'] ?? null);
    }

    /**
     * The models the service offers this account, for services that list
     * them in the OpenAI way. Also proves the key. Null when the service
     * cannot be asked.
     *
     * @return list<string>|null
     *
     * @throws RuntimeException when the service refuses the key or cannot be reached
     */
    public static function models(?string $service, ?string $key, ?string $endpoint): ?array
    {
        $url = self::baseUrl($service, $endpoint);

        if ($url === null || blank($key)) {
            return null;
        }

        try {
            $response = Http::withToken((string) $key)->acceptJson()->connectTimeout(5)->timeout(15)->get($url.'/models');
        } catch (Throwable $exception) {
            throw new RuntimeException('The AI service could not be reached.', previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('The AI service does not accept this key'.($service === 'infomaniak' ? ' and product ID.' : ' and address.'));
        }

        $models = [];

        foreach ((array) $response->json('data') as $model) {
            if (is_array($model) && is_string($model['id'] ?? null)) {
                $models[] = $model['id'];
            }
        }

        sort($models);

        return $models;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function configuration(?string $service, ?string $key, ?string $endpoint, ?string $model): ?array
    {
        if (! isset(self::SERVICES[$service ?? '']) || blank($key)) {
            return null;
        }

        $url = self::baseUrl($service, $endpoint);
        $model = $model ?: self::SERVICES[$service]['model'];

        // Services reached the OpenAI way need to be told where they are and which model to use.
        if (self::SERVICES[$service]['endpoint'] !== null && ($url === null || blank($model))) {
            return null;
        }

        return array_filter([
            'driver' => $url === null ? $service : 'openai-compatible',
            'key' => $key,
            'url' => $url,
            'models' => blank($model) ? null : ['text' => ['default' => $model]],
        ]);
    }

    private static function baseUrl(?string $service, ?string $endpoint): ?string
    {
        return match (true) {
            blank($endpoint) => null,
            $service === 'infomaniak' => 'https://api.infomaniak.com/1/ai/'.rawurlencode(trim((string) $endpoint)).'/openai',
            $service === 'openai-compatible' => rtrim(trim((string) $endpoint), '/'),
            default => null,
        };
    }
}
