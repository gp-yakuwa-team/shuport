<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * API-40 死活確認: 認証なしで 200 と状態が返る（DB 接続の確認を含む）。
     */
    public function test_health_check_returns_ok_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }
}
