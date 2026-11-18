<?php

namespace App\Services\Bandcamp;

use App\Models\BandcampConnection;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Talks to the Bandcamp API on behalf of one label's connection.
 *
 * Bandcamp hands out one grant per client: asking for tokens with the
 * client credentials a second time fails with "duplicate_grant". The
 * refresh token is therefore stored the moment it arrives and used for
 * every later token; the client credentials are only used when no
 * refresh token is stored or Bandcamp no longer accepts it.
 *
 * @see https://bandcamp.com/developer
 *
 * @phpstan-type Band array{band_id: int, name: string, subdomain: string|null, member_bands: list<array{band_id: int, name: string, subdomain: string|null}>}
 */
class BandcampClient
{
    private const string TOKEN_URL = 'https://bandcamp.com/oauth_token';

    private const string BANDS_URL = 'https://bandcamp.com/api/account/1/my_bands';

    private const string SALES_REPORT_URL = 'https://bandcamp.com/api/sales/4/sales_report';

    /** Seconds before expiry at which a token is no longer used. */
    private const int EXPIRY_MARGIN = 60;

    public function __construct(private readonly BandcampConnection $connection) {}

    /**
     * The bands and labels the credentials can act for.
     *
     * @return list<Band>
     */
    public function bands(): array
    {
        $bands = $this->call(self::BANDS_URL, [])['bands'] ?? null;

        if (! is_array($bands)) {
            throw new BandcampException('Bandcamp answered without a list of bands. Try again later.');
        }

        return array_values(array_filter(array_map($this->band(...), $bands)));
    }

    /**
     * Every sales line with start <= sale time < end (UTC).
     *
     * @return list<array<string, mixed>>
     */
    public function salesReport(int $bandId, DateTimeInterface $start, DateTimeInterface $end, ?int $memberBandId = null): array
    {
        $report = $this->call(self::SALES_REPORT_URL, array_filter([
            'band_id' => $bandId,
            'member_band_id' => $memberBandId,
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
        ]))['report'] ?? null;

        if (! is_array($report)) {
            throw new BandcampException('Bandcamp answered without a sales report. Try again later.');
        }

        return array_values(array_filter($report, is_array(...)));
    }

    /**
     * Call an API endpoint, renewing the access token once if Bandcamp
     * no longer accepts it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    private function call(string $url, array $payload): array
    {
        $token = $this->accessToken();
        $response = $this->post($url, $payload, $token);

        if ($response->status() === 401) {
            $response = $this->post($url, $payload, $this->accessToken(rejected: $token));
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new BandcampException('Bandcamp refused the request: '.$this->errorDetail($response, 'these credentials may not access this account').'.');
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new BandcampUnreachableException("HTTP {$response->status()}");
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ($body['error'] ?? false)) {
            throw new BandcampException('Bandcamp reported a problem: '.$this->errorDetail($response, "HTTP {$response->status()}").'.');
        }

        return $body;
    }

    /**
     * Reading a report twice is harmless, so transient failures are retried.
     *
     * @param  array<string, mixed>  $payload
     */
    private function post(string $url, array $payload, string $token): Response
    {
        try {
            return Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(120)
                ->retry([500, 2000], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)), throw: false)
                ->withBody((string) json_encode((object) $payload), 'application/json')
                ->post($url);
        } catch (ConnectionException $exception) {
            throw new BandcampUnreachableException($exception->getMessage());
        }
    }

    /**
     * A usable access token. Renewal is serialised per connection so two
     * processes never ask Bandcamp for tokens at the same time.
     *
     * @param  string|null  $rejected  A token Bandcamp just refused, which must not be handed out again.
     */
    private function accessToken(?string $rejected = null): string
    {
        try {
            return Cache::lock("bandcamp-token:{$this->connection->id}", 60)->block(30, function () use ($rejected): string {
                $this->connection->refresh();
                $stored = $this->connection->access_token;

                if ($stored !== null && $stored !== $rejected && $this->connection->access_token_expires_at?->subSeconds(self::EXPIRY_MARGIN)->isFuture()) {
                    return $stored;
                }

                return $this->renewToken();
            });
        } catch (LockTimeoutException) {
            throw new BandcampException('Another Bandcamp request for this label is still running. Try again in a minute.');
        }
    }

    private function renewToken(): string
    {
        $refreshToken = $this->connection->refresh_token;

        if ($refreshToken === null) {
            return $this->grant(['grant_type' => 'client_credentials']);
        }

        try {
            return $this->grant(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
        } catch (InvalidCredentialsException) {
            // The stored refresh token is no longer accepted. The client
            // credentials only work if the old grant is gone as well;
            // otherwise Bandcamp answers duplicate_grant.
            return $this->grant(['grant_type' => 'client_credentials']);
        }
    }

    /**
     * Ask Bandcamp for tokens and store them before anything else happens,
     * because a lost refresh token can only be replaced by regenerating
     * the client. Not retried: a repeated client_credentials call whose
     * first answer got lost would itself be a duplicate grant.
     *
     * @param  array<string, string>  $fields
     */
    private function grant(array $fields): string
    {
        try {
            $response = Http::asForm()->acceptJson()->connectTimeout(10)->timeout(30)->post(self::TOKEN_URL, [
                ...$fields,
                'client_id' => $this->connection->client_id,
                'client_secret' => $this->connection->client_secret,
            ]);
        } catch (ConnectionException $exception) {
            throw new BandcampUnreachableException($exception->getMessage());
        }

        if ($response->json('error') === 'duplicate_grant') {
            throw new DuplicateGrantException;
        }

        if ($response->clientError() && $response->status() !== 429) {
            throw new InvalidCredentialsException($this->errorDetail($response, null));
        }

        $accessToken = $response->json('access_token');

        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            throw new BandcampUnreachableException("HTTP {$response->status()}");
        }

        $rotated = $response->json('refresh_token');
        $lifetime = $response->json('expires_in');

        $this->connection->update([
            'access_token' => $accessToken,
            'refresh_token' => is_string($rotated) && $rotated !== '' ? $rotated : $this->connection->refresh_token,
            'access_token_expires_at' => now()->addSeconds(is_numeric($lifetime) ? (int) $lifetime : 3600),
        ]);

        return $accessToken;
    }

    /**
     * Bandcamp's own words for an error, shortened and without markup.
     *
     * @return ($fallback is null ? string|null : string)
     */
    private function errorDetail(Response $response, ?string $fallback): ?string
    {
        foreach (['error_message', 'error_description', 'error'] as $key) {
            $detail = $response->json($key);

            if (is_string($detail) && trim($detail) !== '') {
                return mb_substr(strip_tags(trim($detail)), 0, 200);
            }
        }

        return $fallback;
    }

    /**
     * @return Band|null
     */
    private function band(mixed $band): ?array
    {
        if (! is_array($band) || ! is_numeric($band['band_id'] ?? null)) {
            return null;
        }

        return [
            ...$this->account($band),
            'member_bands' => array_values(array_map($this->account(...), array_filter(
                is_array($band['member_bands'] ?? null) ? $band['member_bands'] : [],
                fn (mixed $member): bool => is_array($member) && is_numeric($member['band_id'] ?? null),
            ))),
        ];
    }

    /**
     * @param  array<mixed>  $account
     * @return array{band_id: int, name: string, subdomain: string|null}
     */
    private function account(array $account): array
    {
        $name = $account['name'] ?? null;
        $subdomain = $account['subdomain'] ?? null;

        return [
            'band_id' => (int) $account['band_id'],
            'name' => is_string($name) && $name !== '' ? $name : "Band {$account['band_id']}",
            'subdomain' => is_string($subdomain) && $subdomain !== '' ? $subdomain : null,
        ];
    }
}
