<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => (string) Str::uuid(),
            'user_id' => null,
            'endpoint' => sprintf('https://push.example.com/%s', Str::uuid()),
            'public_key' => base64_encode(Str::random(32)),
            'auth_token' => base64_encode(Str::random(32)),
            'content_encoding' => 'aesgcm',
            'user_agent' => fake()->userAgent(),
            'last_seen_at' => now(),
        ];
    }
}
