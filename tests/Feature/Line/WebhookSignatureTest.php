<?php

namespace Tests\Feature\Line;

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

    public function test_valid_signature_returns_200(): void
    {
        [$body, $signature] = $this->signed(['events' => []]);

        $this->call('POST', '/api/v1/line/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $signature,
        ], $body)->assertOk();
    }

    public function test_invalid_signature_returns_401_and_processes_nothing(): void
    {
        [$body] = $this->signed(['events' => []]);

        $this->call('POST', '/api/v1/line/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => 'invalid-signature',
        ], $body)->assertUnauthorized();
    }

    public function test_missing_signature_returns_401(): void
    {
        $this->postJson('/api/v1/line/webhook', ['events' => []])
            ->assertUnauthorized();
    }
}
