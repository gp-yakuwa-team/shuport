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
     * @return array<string, mixed>
     *
     * @throws AiServiceException 使える AI がない、または呼び出しに失敗したとき
     */
    public function generateJson(string $purpose, string $prompt): array
    {
        try {
            return $this->gemini->generateJson($prompt);
        } catch (AiServiceException $e) {
            if (! in_array($purpose, self::FALLBACK_PURPOSES, true)) {
                throw $e;
            }

            return $this->workersAi->generateJson($prompt);
        }
    }
}
