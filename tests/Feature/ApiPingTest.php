<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ApiPingTest extends TestCase
{
    public function test_it_returns_the_api_ping_response(): void
    {
        Config::set('app.name', 'TapCloudi Test');

        $this->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'app' => 'TapCloudi Test',
                    'environment' => app()->environment(),
                ],
                'message' => 'pong',
                'status' => 200,
            ]);
    }
}
