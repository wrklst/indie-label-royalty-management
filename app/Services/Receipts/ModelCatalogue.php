<?php

namespace App\Services\Receipts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The models each AI service offers that can read a receipt: those that
 * take images. Names, prices and descriptions come from models.dev, a
 * public catalogue kept by the community, and are fetched once a day.
 *
 * @phpstan-type CatalogueModel array{id: string, name: string, description: string|null, input_price: float, output_price: float, per_hundred: float, fixed_format: bool, reasoning: bool, released: string|null}
 */
class ModelCatalogue
{
    private const string URL = 'https://models.dev/api.json';

    /** Our service name => its name in the catalogue. */
    private const array PROVIDERS = ['infomaniak' => 'infomaniak', 'openai' => 'openai', 'anthropic' => 'anthropic', 'mistral' => 'mistral', 'gemini' => 'google'];

    /** A receipt as the service counts it: the picture and the instructions in, the fields out. */
    private const int TOKENS_IN = 3000;

    private const int TOKENS_OUT = 150;

    /**
     * Models by service, cheapest first. Empty for a service the
     * catalogue does not know or when it cannot be reached.
     *
     * @return array<string, list<CatalogueModel>>
     */
    public static function all(): array
    {
        $cached = Cache::get('receipt-models');

        if (is_array($cached)) {
            return $cached;
        }

        $models = self::fetch();

        // A catalogue that could not be fetched is asked for again soon.
        Cache::put('receipt-models', $models, $models === [] ? now()->addMinutes(10) : now()->addDay());

        return $models;
    }

    /**
     * @return array<string, list<CatalogueModel>>
     */
    private static function fetch(): array
    {
        try {
            $catalogue = Http::acceptJson()->connectTimeout(5)->timeout(20)->get(self::URL)->json();
        } catch (Throwable) {
            return [];
        }

        $services = [];

        foreach (self::PROVIDERS as $service => $provider) {
            $models = [];

            foreach ((array) ($catalogue[$provider]['models'] ?? []) as $id => $model) {
                $input = $model['cost']['input'] ?? null;
                $output = $model['cost']['output'] ?? null;

                if (! is_array($model) || ! is_numeric($input) || ! is_numeric($output) || (float) $output <= 0
                    || ! in_array('image', (array) ($model['modalities']['input'] ?? []), true)
                    || (array) ($model['modalities']['output'] ?? []) !== ['text']
                    || ($model['status'] ?? null) === 'deprecated') {
                    continue;
                }

                $models[] = [
                    'id' => (string) $id,
                    'name' => (string) ($model['name'] ?? $id),
                    'description' => is_string($model['description'] ?? null) ? $model['description'] : null,
                    'input_price' => (float) $input,
                    'output_price' => (float) $output,
                    // US dollars for a hundred receipts.
                    'per_hundred' => round((self::TOKENS_IN * (float) $input + self::TOKENS_OUT * (float) $output) / 10000, 2),
                    'fixed_format' => ($model['structured_output'] ?? null) === true,
                    'reasoning' => ($model['reasoning'] ?? null) === true,
                    'released' => is_string($model['release_date'] ?? null) ? $model['release_date'] : null,
                ];
            }

            usort($models, fn (array $a, array $b): int => [$a['per_hundred'], $a['name']] <=> [$b['per_hundred'], $b['name']]);
            $services[$service] = $models;
        }

        return array_filter($services) === [] ? [] : $services;
    }
}
