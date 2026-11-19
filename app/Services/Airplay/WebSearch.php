<?php

namespace App\Services\Airplay;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Google results through the DataForSEO SERP API. Every search is billed
 * by DataForSEO, so it only runs when a user asks for it.
 */
class WebSearch
{
    private const string URL = 'https://api.dataforseo.com/v3/serp/google/organic/live/advanced';

    private const int OK = 20000;

    /** Task codes from here on mean the request was refused: wrong login, no credit, bad input. */
    private const int REQUEST_ERROR = 40000;

    /** Task codes from here on are failures on the service's side, worth another try. */
    private const int SERVICE_ERROR = 50000;

    private const int ATTEMPTS = 3;

    public function isConfigured(): bool
    {
        return filled(config('services.dataforseo.login')) && filled(config('services.dataforseo.password'));
    }

    /**
     * @param  int  $depth  How many results to ask for; every ten are billed as one search.
     * @return list<array{title: string, url: string, description: string|null, domain: string|null}>
     *
     * @throws RuntimeException when the search could not be run
     */
    public function search(string $query, int $depth = 10): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Web search is not set up: DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD are missing.');
        }

        // The service's own search engine fails often ("Internal SE Server Error"); failed tries are not billed.
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $task = $this->task($query, $depth);

            if ((int) ($task['status_code'] ?? 0) < self::SERVICE_ERROR) {
                break;
            }
        }

        // A 2xxxx code other than OK is a partial answer: fewer pages than asked for, which is still worth having.
        if ((int) ($task['status_code'] ?? 0) >= self::REQUEST_ERROR || ! isset($task['result'])) {
            throw new RuntimeException('The search failed: '.($task['status_message'] ?? 'no answer'));
        }

        return collect($task['result'][0]['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'organic' && filled($item['url'] ?? null))
            ->map(fn (array $item): array => [
                'title' => (string) ($item['title'] ?? $item['url']),
                'url' => (string) $item['url'],
                'description' => $item['description'] ?? null,
                'domain' => $item['domain'] ?? null,
            ])->values()->all();
    }

    /**
     * Run one search task and return what the service says about it.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the service cannot be reached or refuses the request
     */
    private function task(string $query, int $depth): array
    {
        try {
            $response = Http::withBasicAuth(config('services.dataforseo.login'), config('services.dataforseo.password'))
                ->acceptJson()->connectTimeout(10)->timeout(70)
                ->post(self::URL, [[
                    'keyword' => mb_substr($query, 0, 700),
                    'location_code' => (int) config('services.dataforseo.location_code'),
                    'language_code' => config('services.dataforseo.language_code'),
                    'depth' => $depth,
                    // Without this Google's results are session-bound redirect links that stop working.
                    // It only works on the "advanced" endpoint and makes a search take about half a minute.
                    'resolve_goto_urls' => true,
                ]]);
        } catch (Throwable $exception) {
            throw new RuntimeException('The search service could not be reached.', previous: $exception);
        }

        $task = $response->json('tasks.0');

        if ($response->failed() || $response->json('status_code') !== self::OK || ! is_array($task)) {
            throw new RuntimeException('The search failed: '.($response->json('status_message') ?? "HTTP {$response->status()}"));
        }

        return $task;
    }
}
