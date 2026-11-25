<?php

namespace App\Support\Ai;

/**
 * AI 呼び出しの入口。メインAI（Gemini）を先に使い、失敗したときだけ
 * 対象の機能をサブAI（Workers AI）へ1回切り替える（API定義書 v18 第3章）。
 *
 * 切り替えの対象は「業界の候補」「ES生成」「LINEのトーク解析」だけ。
 * 「企業研究」「最新トピックス再取得」は切り替えず、失敗をそのまま返す
 * （呼び出し側は復旧後の再実行で扱う。企業研究は、メインAIが使えず
 *  始めなかった場合は回数に数えない）。
 *
 * 回数の記録と上限の判定は AiUsageGate が担い、ここでは行わない。
 */
class AiGateway
{
    /**
     * サブAIへ切り替える対象の purpose（ai_usage_logs の purpose と同じ文字列）。
     *
     * @var list<string>
     */
    private const FALLBACK_PURPOSES = ['業界の候補', 'ES生成', 'LINEのトーク解析'];

    public function __construct(
        private readonly GeminiApiService $gemini,
        private readonly WorkersAiApiService $workersAi,
    ) {}

    /**
     * 切り替え先を含め、どちらかの AI が使える状態かどうか。
     */
    public function isAvailable(): bool
    {
        return $this->gemini->isAvailable() || $this->workersAi->isAvailable();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AiServiceException 使える AI がない、または呼び出しに失敗したとき
     */
    public function generateJson(string $purpose, string $prompt): array
    {
        return $this->generateResult($purpose, $prompt)->data;
    }

    /**
     * 応答本文と、実際に応答したモデル・トークン数を返す。
     * 時間切れは機能ごとに渡す（Gemini と Workers AI で別々）。
     *
     * @throws AiServiceException
     */
    public function generateResult(
        string $purpose,
        string $prompt,
        ?int $geminiTimeoutSeconds = null,
        ?int $workersTimeoutSeconds = null,
    ): AiResult {
        try {
            return $this->gemini->generateResult($prompt, $geminiTimeoutSeconds);
        } catch (AiServiceException $e) {
            if (! in_array($purpose, self::FALLBACK_PURPOSES, true)) {
                throw $e;
            }

            return $this->workersAi->generateResult($prompt, $workersTimeoutSeconds);
        }
    }
}
