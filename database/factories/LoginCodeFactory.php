<?php

namespace Database\Factories;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginCode>
 */
class LoginCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code_hash' => fn (array $attributes): string => LoginCode::hash($attributes['user_id'], '123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(LoginCode::MINUTES_VALID),
        ];
    }

    /**
     * Set the code that signs the user in.
     */
    public function code(string $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'code_hash' => fn (array $attributes): string => LoginCode::hash($attributes['user_id'], $code),
        ]);
    }

    /**
     * Indicate that the code is past its lifetime.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subSecond(),
        ]);
    }
}
