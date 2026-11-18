<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * LINE のテキストメッセージの解析（連携コードの照合、または予定候補の解析）。
 * ai_jobs には行を作らない（対象は企業研究・ES生成・最新トピックス再取得のみ）。
 * AI を呼び出す場合は「LINEのトーク解析」として AiUsageGate で記録・判定する。
 */
class ParseLineMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * タイムアウトは20秒（決定事項。テストで見直す）。
     */
    public int $timeout = 20;

    /**
     * @param  array<string, mixed>  $event  Webhook のイベント1件分（生の Payload 全体は保存しない）
     */
    public function __construct(
        public array $event,
    ) {}

    public function handle(): void
    {
        // TODO(D2): 連携コードの照合、または予定候補を解析して pending_line_events へ保存し、
        // 確認メッセージを返す。チェック制約に合わない内容はレコードを作らず、
        // 「日程を取得できませんでした。内容を確認してください」と返信する。
    }
}
