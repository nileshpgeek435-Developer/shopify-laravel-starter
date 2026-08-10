<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'demo.myshopify.com',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'shpat_test_token_1234567890',
            'remember_token' => Str::random(10),
            'shopify_grandfathered' => false,
            'shopify_freemium' => false,
        ];
    }

    /**
     * A shop with an offline access token.
     */
    public function withOfflineToken(string $token = 'shpat_test_token_1234567890'): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => $token,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
