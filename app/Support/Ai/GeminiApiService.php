<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\Http;

/**
 * メインAI（Gemini）のクライアント。
 * 機能ごとのプロンプトと入出力の形式は AI設計書 V5 が正本で、ここでは
 * 「プロンプトを送って JSON の応答を配列で受け取る」ところだけを担う。
 */
class GeminiApiService
{
    /**
     * 呼び出しができる状態かどうか。
     * キーが未設定のときと、課金プロジェクトでの規約確認が済んでいないとき
     * （決定12）は false を返し、呼び出し側は「メインAIが使えない」として扱う。
     */
    public function isAvailable(): bool
    {
        return ! empty(config('ai.gemini.api_key'))
            && (bool) config('ai.gemini.paid_project_verified');
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
            throw new AiServiceException('Gemini is not available (API key missing or paid project not verified).');
        }

        $model = (string) config('ai.gemini.model');

        $response = Http::withHeaders([
            'x-goog-api-key' => (string) config('ai.gemini.api_key'),
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            throw new AiServiceException("Gemini request failed with status {$response->status()}.");
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new AiServiceException('Gemini response did not contain text content.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new AiServiceException('Gemini response was not valid JSON.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
