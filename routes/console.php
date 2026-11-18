<?php

use App\Jobs\CleanupExpiredDataJob;
use App\Jobs\PurgeExpiredDataJob;
use App\Jobs\SendDailyLineDigestJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// LINE の定時 Push: 20:00〜20:30（JST）に5分ごとに確かめ、その日にまだ送っていない人にだけ送る。
// 起動はコンテナ内の schedule:work（外部の cron は使わない）。
// 20:00〜20:30 はデプロイとキャッシュの消去をしない（送信済みの記録が cache テーブルにあるため）。
Schedule::job(new SendDailyLineDigestJob)
    ->everyFiveMinutes()
    ->between('20:00', '20:30')
    ->timezone('Asia/Tokyo');

// 期限を過ぎたデータの自動削除: 毎日 3:00（JST）。日次バックアップ（4:17）より前に走る。
Schedule::job(new CleanupExpiredDataJob)
    ->dailyAt('03:00')
    ->timezone('Asia/Tokyo');

// 公開終了時のデータ削除: 公開終了日時（PUBLIC_END_AT）に達したかを確かめる。
// 実際の削除条件はジョブ側で判定し、未設定・未到達の間は何もしない。
Schedule::job(new PurgeExpiredDataJob)
    ->everyMinute()
    ->timezone('Asia/Tokyo');
