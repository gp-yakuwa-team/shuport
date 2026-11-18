<?php

namespace App\Jobs;

use App\Support\ErrorRecorder;
use App\Support\Supabase\AdminClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 公開終了時のデータ削除（置き場）。
 * 公開終了日時（環境変数 PUBLIC_END_AT、展示会最終日の 23:59 JST）に達したら、
 * 利用者を1人ずつ Supabase の管理用 API で物理削除する（public のテーブルは
 * ON DELETE CASCADE で消える）。あわせて jobs・failed_jobs・cache・error_logs・
 * deleted_accounts の全行を削除する。
 *
 * 分担（バックアップ運用方針）:
 * - このジョブ: アプリ側のデータ削除
 * - GitHub Actions の「終了処理」ワークフロー: バックアップの削除
 * - 手作業: 新規登録の停止（終了日時の直前）、鍵と外部サービスの認証情報の破棄、
 *   Gemini API の請求先の取り外し
 *
 * 終了日時は未確定（基本設計書 第14章 No.5）。PUBLIC_END_AT が未設定の間は
 * 何もしない。
 */
class PurgeExpiredDataJob implements ShouldQueue
{
    use Queueable;

    public function handle(AdminClient $admin): void
    {
        if (! $this->reachedEnd()) {
            return;
        }

        try {
            // 利用者を1人ずつ Supabase の管理用 API で物理削除する
            // （public 側の行は ON DELETE CASCADE で消える）。
            // 削除の記録（deleted_accounts）は、この全行削除より前に退会処理で作られたもの。
            foreach (DB::table('users')->pluck('id') as $userId) {
                $admin->deleteUser((string) $userId);
            }

            DB::table('jobs')->delete();
            DB::table('failed_jobs')->delete();
            DB::table('cache')->delete();
            DB::table('cache_locks')->delete();
            DB::table('error_logs')->delete();
            DB::table('deleted_accounts')->delete();
        } catch (Throwable $e) {
            ErrorRecorder::record($e, '定期処理');

            throw $e;
        }
    }

    private function reachedEnd(): bool
    {
        $endAt = config('shuport.public_end_at');

        if (! is_string($endAt) || $endAt === '') {
            return false;
        }

        return CarbonImmutable::now('Asia/Tokyo')
            ->greaterThanOrEqualTo(CarbonImmutable::parse($endAt, 'Asia/Tokyo'));
    }
}
