<?php

namespace Tests\Feature\Line;

use App\Jobs\ParseLineMessageJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    private string $secret = 'test-channel-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['line.channel_secret' => $this->secret]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string} [JSON body, signature]
     */
    private function signed(array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $body, $this->secret, true));

        return [$body, $signature];
    }

    public function test_valid_signature_returns_200_and_dispatches_job(): void
    {
        Queue::fake();

        [$body, $signature] = $this->signed([
            'events' => [
                [
                    'type' => 'message',
                    'replyToken' => 'token-1',
                    'source' => ['userId' => 'U123'],
                    'message' => ['type' => 'text', 'text' => '123456'],
                ],
            ],
        ]);

        $this->call('POST', '/api/v1/line/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $signature,
        ], $body)->assertOk();

        Queue::assertPushed(ParseLineMessageJob::class, 1);
    }

    public function test_invalid_signature_returns_401_and_dispatches_nothing(): void
    {
        Queue::fake();

        [$body] = $this->signed(['events' => []]);

        $this->call('POST', '/api/v1/line/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => 'invalid-signature',
        ], $body)->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_missing_signature_returns_401(): void
    {
        $this->postJson('/api/v1/line/webhook', ['events' => []])
            ->assertUnauthorized();
    }
}
