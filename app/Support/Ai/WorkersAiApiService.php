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
        return $this->generateResult($prompt)->data;
    }

    /**
     * 応答本文に加えて、実際に使ったモデルとトークン数を返す。
     *
     * @throws AiServiceException
     */
    public function generateResult(string $prompt, ?int $timeoutSeconds = null): AiResult
    {
        if (! $this->isAvailable()) {
            throw new AiServiceException('Workers AI is not available (account id or token missing).');
        }

        $accountId = (string) config('ai.workers_ai.account_id');
        $model = (string) config('ai.workers_ai.model');

        $response = Http::withToken((string) config('ai.workers_ai.api_token'))
            ->timeout($timeoutSeconds ?? 30)
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
            return new AiResult(
                $result,
                $model,
                $this->tokenCount($response->json('result.usage.prompt_tokens')),
                $this->tokenCount($response->json('result.usage.completion_tokens')),
            );
        }

        if (is_string($result)) {
            $decoded = json_decode($result, true);

            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return new AiResult(
                    $decoded,
                    $model,
                    $this->tokenCount($response->json('result.usage.prompt_tokens')),
                    $this->tokenCount($response->json('result.usage.completion_tokens')),
                );
            }
        }

        throw new AiServiceException('Workers AI response was not valid JSON.');
    }

    private function tokenCount(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }
}
