<?php

namespace Tests\Feature\Line;

use App\Support\Line\LineApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'line.channel_access_token' => 'test-access-token',
            'line.monthly_push_limit' => 200,
        ]);
    }

    public function test_push_sends_message_with_token(): void
    {
        Http::fake([
            'api.line.me/*' => Http::response([]),
        ]);

        app(LineApiService::class)->push('U123', [LineApiService::textMessage('hello')]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/push')
            && $request['to'] === 'U123'
            && $request['messages'][0]['text'] === 'hello');
    }

    public function test_monthly_limit_reached_at_200(): void
    {
        Http::fake([
            'api.line.me/*' => Http::response(['totalUsage' => 200]),
        ]);

        $this->assertTrue(app(LineApiService::class)->monthlyLimitReached());
    }

    public function test_monthly_limit_not_reached_below_200(): void
    {
        Http::fake([
            'api.line.me/*' => Http::response(['totalUsage' => 42]),
        ]);

        $this->assertFalse(app(LineApiService::class)->monthlyLimitReached());
    }
}
