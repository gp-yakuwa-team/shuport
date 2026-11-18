<?php

use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\EntrySheetController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\LineWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API ルート（/api/v1）
|--------------------------------------------------------------------------
|
| API定義書 第6章の受け口。認証（Supabase Auth の JWT 検証とセッションの
| 有効性チェック）は B3・B4 の認証基盤が入り次第、このグループに付ける。
| API-12（LINE Webhook）だけは認証の例外で、LINE の署名検証で確かめる。
|
*/

Route::prefix('v1')->group(function () {
    // API-07 企業登録＆AI研究開始判断
    Route::post('/companies', [CompanyController::class, 'store']);

    // API-09 企業研究の開始・再実行
    Route::post('/companies/{company_id}/research', [CompanyController::class, 'research']);

    // API-10 最新トピックス再取得
    Route::post('/companies/{company_id}/topics/refresh', [CompanyController::class, 'refreshTopics']);

    // API-08 ES新規作成＆一括AI生成開始
    Route::post('/entry-sheets', [EntrySheetController::class, 'store']);

    // API-13 既存ESの一括AI生成開始
    Route::post('/entry-sheets/{entry_sheet_id}/generate', [EntrySheetController::class, 'generate']);

    // API-11 ジョブ進捗取得
    Route::get('/jobs/{job_id}', [JobController::class, 'show']);

    // API-12 LINE Webhook 受信
    Route::post('/line/webhook', LineWebhookController::class);
});
