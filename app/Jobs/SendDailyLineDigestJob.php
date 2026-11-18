<?php

namespace App\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * LINE の定時 Push（翌日の予定・3日以内の ES 締切・未完了タスクを1通にまとめる）。
 * 20:00〜20:30 に5分ごとにスケジューラから投入され（C4）、
 * その日にまだ送っていない人にだけ送る。送信済みの記録は cache テーブルに
 * 利用者ごと・日付ごとで置く（決定事項）。月の送信数が上限に達している間は送らない。
 * 対象の項目がない連携ユーザーは送らない。
 */
class SendDailyLineDigestJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // TODO(D2): 送信先の選定（LINE 連携済みで、停止中でない人）と送信。
        // 送信したら markSent() で記録し、hasSent() で二重送信を防ぐ。
        // 送信数の上限と停止状態は LINE API と app_states で確認する。
    }

    public function hasSent(string $lineUserId, string $date): bool
    {
        return Cache::has(self::sentKey($lineUserId, $date));
    }

    public function markSent(string $lineUserId, string $date): void
    {
        // 記録の期限は翌日の 0:00（JST）
        $expiresAt = CarbonImmutable::now('Asia/Tokyo')->addDay()->startOfDay();

        Cache::put(self::sentKey($lineUserId, $date), true, $expiresAt);
    }

    /**
     * キーは LINEユーザーID のハッシュ値と日本時間の日付（決定事項）。
     */
    private static function sentKey(string $lineUserId, string $date): string
    {
        return 'line-digest:sent:'.hash('sha256', $lineUserId).':'.$date;
    }
}
