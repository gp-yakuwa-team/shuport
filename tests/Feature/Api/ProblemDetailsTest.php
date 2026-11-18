<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    /**
     * 共通エラーハンドリング（C1）: 存在しない API は problem+json の 404 を返す。
     */
    public function test_unknown_api_returns_problem_details_404(): void
    {
        $response = $this->getJson('/api/v1/unknown-endpoint');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance']);
        $response->assertJsonPath('status', 404);
    }

    /**
     * API の枠（C6）: 未実装の受け口は problem+json の 501 を返す。
     * LINE Webhook（API-12）は認証の対象外のため、未実装の間はこの形になる。
     * D2（LINE連携の基盤）で実装に置き換わったら、このテストも更新する。
     */
    public function test_unimplemented_endpoint_returns_problem_details_501(): void
    {
        $response = $this->postJson('/api/v1/line/webhook', []);

        $response->assertStatus(501);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonPath('status', 501);
    }
}
