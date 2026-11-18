<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    /**
     * 認証ガード（B4）: トークンがない場合は problem+json の 401 を返す。
     */
    public function test_protected_endpoint_requires_a_token(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance']);
        $response->assertJsonPath('status', 401);
    }

    /**
     * 認証ガード（B4）: 検証できないトークンは 401 を返す。
     */
    public function test_protected_endpoint_rejects_an_invalid_token(): void
    {
        $response = $this->getJson('/api/v1/dashboard', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('status', 401);
    }
}
