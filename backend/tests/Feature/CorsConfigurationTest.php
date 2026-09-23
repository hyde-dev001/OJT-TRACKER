<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    public function test_configured_frontend_origin_is_allowed_for_api_requests(): void
    {
        $this->getJson('/api/health', [
            'Origin' => 'https://frontend.example.test',
        ])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://frontend.example.test');
    }
}
