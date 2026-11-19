<?php

namespace Tests\Feature\Ai;

use App\Support\Ai\AiGateway;
use App\Support\Ai\AiServiceException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.gemini.api_key' => 'test-gemini-key',
            'ai.gemini.model' => 'gemini-3.5-flash-lite',
            'ai.gemini.paid_project_verified' => true,
            'ai.workers_ai.account_id' => 'test-account',
            'ai.workers_ai.api_token' => 'test-cf-token',
            'ai.workers_ai.model' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
        ]);
    }

    public function test_gemini_success_returns_decoded_json(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '{"summary":"ok"}']]]],
                ],
            ]),
        ]);

        $result = app(AiGateway::class)->generateJson('ES生成', 'prompt');

        $this->assertSame(['summary' => 'ok'], $result);
    }

    public function test_fallback_to_workers_ai_for_es_generation(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.cloudflare.com/*' => Http::response([
                'result' => ['response' => '{"draft":"from-sub"}'],
            ]),
        ]);

        $result = app(AiGateway::class)->generateJson('ES生成', 'prompt');

        $this->assertSame(['draft' => 'from-sub'], $result);
    }

    public function test_no_fallback_for_company_research(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.cloudflare.com/*' => Http::response([
                'result' => ['response' => '{"draft":"should-not-be-used"}'],
            ]),
        ]);

        $this->expectException(AiServiceException::class);

        try {
            app(AiGateway::class)->generateJson('企業研究', 'prompt');
        } finally {
            Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.cloudflare.com'));
        }
    }

    public function test_gemini_unavailable_when_paid_project_not_verified(): void
    {
        config(['ai.gemini.paid_project_verified' => false]);

        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'result' => ['response' => '{"draft":"from-sub"}'],
            ]),
        ]);

        // 規約確認が済むまでは Gemini を呼ばず、切り替え対象はサブAIへ回る。
        $result = app(AiGateway::class)->generateJson('業界の候補', 'prompt');

        $this->assertSame(['draft' => 'from-sub'], $result);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }
}
