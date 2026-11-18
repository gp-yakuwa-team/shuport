<?php

namespace App\Jobs;

use App\Support\ErrorRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 期限を過ぎたデータの毎日の自動削除（DB定義書 v7）。
 * 毎日 3:00（JST）にスケジューラから投入される。自動削除（3:00）は
 * 日次バックアップ（4:17）より前に走る。
 */
class CleanupExpiredDataJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        try {
            $now = now();

            // 保持14日: エラーログ、完了・失敗した AI ジョブ、失敗したジョブ
            DB::table('error_logs')->where('occurred_at', '<', $now->copy()->subDays(14))->delete();
            DB::table('ai_jobs')->whereIn('status', ['完了', '失敗'])->where('finished_at', '<', $now->copy()->subDays(14))->delete();
            DB::table('failed_jobs')->where('failed_at', '<', $now->copy()->subDays(14))->delete();

            // 保持31日: 退会済みの記録
            DB::table('deleted_accounts')->where('deleted_at', '<', $now->copy()->subDays(31))->delete();

            // 期限切れ: LINE 連携コード、確認待ちの予定候補、キャッシュ
            DB::table('line_one_time_codes')->where('expires_at', '<', $now)->delete();
            DB::table('pending_line_events')->where('expires_at', '<', $now)->delete();
            DB::table('cache')->where('expiration', '<', $now->timestamp)->delete();
        } catch (Throwable $e) {
            ErrorRecorder::record($e, '定期処理');

            throw $e;
        }
    }
}
