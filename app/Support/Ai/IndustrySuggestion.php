<?php

namespace App\Support\Ai;

use App\Models\Company;
use App\Models\User;
use App\Support\AiUsageGate;
use App\Support\ErrorRecorder;

/**
 * API-17 業界の候補（P-04）。同期で AI を1回呼び、17区分のいずれか、または null を返す。
 * 上限・休止・失敗のときも画面を妨げないよう、呼び出し側は 200 で industry=null を返す。
 */
class IndustrySuggestion
{
    public function __construct(
        private readonly AiGateway $gateway,
    ) {}

    /**
     * @return string|null 17区分の文字列。分類できない・AI を使えないときは null
     */
    public function suggest(User $user, string $companyName): ?string
    {
        if (! $this->withinLimit($user) || ! $this->gateway->isAvailable()) {
            return null;
        }

        try {
            // 総期限15秒のうち、Gemini に12秒、切り替え先の Workers AI に残り3秒を割り当てる。
            $result = $this->gateway->generateResult(
                '業界の候補',
                Prompt::render('P-04', 'industry_suggestion', [
                    'company_name' => $companyName,
                ]),
                12,
                3,
            );
        } catch (AiServiceException $e) {
            ErrorRecorder::record($e, '外部サービス');

            return null;
        }

        $industry = $result->data['industry'] ?? null;

        if (! is_string($industry) || ! in_array($industry, Company::INDUSTRIES, true)) {
            return null;
        }

        // 候補が得られた場合だけ数える（詳細設計書 v5 付録A A.4・API-17）。
        $log = AiUsageGate::record(
            $user->id,
            $result->model,
            '業界の候補',
        );

        $log->update([
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
        ]);

        return $industry;
    }

    private function withinLimit(User $user): bool
    {
        if ($user->is_demo) {
            return AiUsageGate::demoAllowed();
        }

        return AiUsageGate::generationAllowed($user->id);
    }
}
