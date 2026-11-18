<?php

namespace Database\Factories;

use App\Models\BandcampConnection;
use App\Models\Label;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BandcampConnection>
 */
class BandcampConnectionFactory extends Factory
{
    /**
     * A connected label with a band chosen and a valid access token.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label_id' => Label::factory(),
            'client_id' => '4711',
            'client_secret' => 'example-client-secret',
            'refresh_token' => 'example-refresh-token',
            'access_token' => 'example-access-token',
            'access_token_expires_at' => now()->addHour(),
            'bands' => [['band_id' => 1000001, 'name' => 'Example Records', 'subdomain' => 'example-records', 'member_bands' => []]],
            'band_id' => 1000001,
            'enabled' => true,
            'sync_from' => '2026-01-01',
        ];
    }

    /**
     * Credentials stored, but no token obtained yet.
     */
    public function withoutTokens(): static
    {
        return $this->state(['refresh_token' => null, 'access_token' => null, 'access_token_expires_at' => null]);
    }

    /**
     * The access token ran out; only the refresh token is usable.
     */
    public function expired(): static
    {
        return $this->state(['access_token' => 'expired-access-token', 'access_token_expires_at' => now()->subMinute()]);
    }
}
