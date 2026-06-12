<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public_and_ok(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJson(['status' => 'OK', 'message' => 'API is running']);
    }

    public function test_unknown_api_route_returns_404_json(): void
    {
        $this->getJson('/api/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Route not found.');
    }
}
