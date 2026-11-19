<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\Http;

/**
 * サブAI（Cloudflare Workers AI）のクライアント。
 * 入出力は GeminiApiService と同じ形（プロンプト → JSON の配列）にそろえる。
 */
class WorkersAiApiService
{
    public function isAvailable(): bool
    {
        return ! empty(config('ai.workers_ai.account_id'))
            && ! empty(config('ai.workers_ai.api_token'));
    }

    /**
     * プロンプトを送り、JSON 形式の応答をデコードして返す。
     *
     * @return array<string, mixed>
     *
     * @throws AiServiceException 呼び出しに失敗した、または応答を JSON として読めなかったとき
     */
    public function generateJson(string $prompt): array
    {
        if (! $this->isAvailable()) {
            throw new AiServiceException('Workers AI is not available (account id or token missing).');
        }

        $accountId = (string) config('ai.workers_ai.account_id');
        $model = (string) config('ai.workers_ai.model');

        $response = Http::withToken((string) config('ai.workers_ai.api_token'))
            ->post("https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}", [
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new AiServiceException("Workers AI request failed with status {$response->status()}.");
        }

        $result = $response->json('result.response');

        if (is_array($result)) {
            /** @var array<string, mixed> $result */
            return $result;
        }

        if (is_string($result)) {
            $decoded = json_decode($result, true);

            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        throw new AiServiceException('Workers AI response was not valid JSON.');
    }
}
