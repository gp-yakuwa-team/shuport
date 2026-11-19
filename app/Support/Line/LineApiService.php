<?php

namespace App\Support\Line;

use Illuminate\Support\Facades\Http;

/**
 * LINE Messaging API のクライアント（送信と送信数の確認）。
 * 定時Push の送信済みの管理は SendDailyLineDigestJob 側で行い、
 * ここでは API の呼び出しだけを担う。
 */
class LineApiService
{
    private const BASE_URL = 'https://api.line.me/v2/bot/message';

    public function isAvailable(): bool
    {
        return ! empty(config('line.channel_access_token'));
    }

    /**
     * テキストメッセージの形を作る。
     *
     * @return array<string, string>
     */
    public static function textMessage(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    /**
     * Webhook の replyToken へ返信する。
     *
     * @param  list<array<string, mixed>>  $messages
     *
     * @throws LineApiException
     */
    public function reply(string $replyToken, array $messages): void
    {
        $this->post('/reply', [
            'replyToken' => $replyToken,
            'messages' => $messages,
        ]);
    }

    /**
     * 指定した LINE ユーザーへ Push 送信する。
     *
     * @param  list<array<string, mixed>>  $messages
     *
     * @throws LineApiException
     */
    public function push(string $lineUserId, array $messages): void
    {
        $this->post('/push', [
            'to' => $lineUserId,
            'messages' => $messages,
        ]);
    }

    /**
     * 今月の送信数（LINE の API で取得する）。
     *
     * @throws LineApiException
     */
    public function monthlyConsumption(): int
    {
        $response = Http::withToken($this->token())
            ->get(self::BASE_URL.'/quota/consumption');

        if ($response->failed()) {
            throw new LineApiException("LINE quota request failed with status {$response->status()}.");
        }

        return (int) $response->json('totalUsage', 0);
    }

    /**
     * 月の上限（200通）に達しているかどうか。
     *
     * @throws LineApiException
     */
    public function monthlyLimitReached(): bool
    {
        return $this->monthlyConsumption() >= (int) config('line.monthly_push_limit', 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws LineApiException
     */
    private function post(string $path, array $payload): void
    {
        $response = Http::withToken($this->token())
            ->post(self::BASE_URL.$path, $payload);

        if ($response->failed()) {
            throw new LineApiException("LINE request to {$path} failed with status {$response->status()}.");
        }
    }

    /**
     * @throws LineApiException
     */
    private function token(): string
    {
        $token = (string) config('line.channel_access_token');

        if ($token === '') {
            throw new LineApiException('LINE channel access token is not configured.');
        }

        return $token;
    }
}
