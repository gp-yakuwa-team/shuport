<?php

namespace App\Support;

use App\Models\AiUsageLog;
use Carbon\CarbonImmutable;

class AiUsageGate
{
    /**
     * 「AI生成」枠で数える purpose（API定義書 第5章）。
     * 直前1時間に20回まで。
     */
    private const GENERATION_PURPOSES = ['業界の候補', 'ES生成', 'LINEのトーク解析'];

    private const GENERATION_LIMIT = 20;

    /**
     * 「企業研究」枠で数える purpose（API定義書 第5章）。
     * JST の1日に20回まで。再利用した場合と、メインAIが使えず研究を始めなかった場合は数えない
     * （＝行を作らない）ため、ここでは行の数だけを見る。
     */
    private const RESEARCH_PURPOSES = ['企業研究', '最新トピックス再取得'];

    private const RESEARCH_LIMIT = 20;

    /**
     * 体験用アカウント全体の上限（purpose を問わず JST の1日に100回まで）。
     */
    private const DEMO_DAILY_LIMIT = 100;

    public static function generationAllowed(string $userId): bool
    {
        $count = AiUsageLog::query()
            ->where('user_id', $userId)
            ->whereIn('purpose', self::GENERATION_PURPOSES)
            ->where('used_at', '>=', now()->subHour())
            ->count();

        return $count < self::GENERATION_LIMIT;
    }

    public static function researchAllowed(string $userId): bool
    {
        [$start, $end] = self::jstTodayRange();

        $count = AiUsageLog::query()
            ->where('user_id', $userId)
            ->whereIn('purpose', self::RESEARCH_PURPOSES)
            ->whereBetween('used_at', [$start, $end])
            ->count();

        return $count < self::RESEARCH_LIMIT;
    }

    public static function demoAllowed(): bool
    {
        [$start, $end] = self::jstTodayRange();

        $count = AiUsageLog::query()
            ->whereHas('user', fn ($query) => $query->where('is_demo', true))
            ->whereBetween('used_at', [$start, $end])
            ->count();

        return $count < self::DEMO_DAILY_LIMIT;
    }

    /**
     * AI の利用を記録する。頼んだ時点で行を作り、上限に達している場合は行を作らずに断る
     * （＝呼び出し側が *Allowed を確認してから record する）。AI が失敗しても行は消さない。
     */
    public static function record(string $userId, string $modelName, string $purpose, ?int $inputTokens = null, ?int $outputTokens = null): AiUsageLog
    {
        return AiUsageLog::create([
            'user_id' => $userId,
            'model_name' => $modelName,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'used_at' => now(),
            'purpose' => $purpose,
        ]);
    }

    /**
     * JST の今日の範囲を UTC の時刻で返す（used_at は timestamptz のため）。
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function jstTodayRange(): array
    {
        $start = CarbonImmutable::now('Asia/Tokyo')->startOfDay();

        return [
            $start->setTimezone('UTC'),
            $start->endOfDay()->setTimezone('UTC'),
        ];
    }
}
