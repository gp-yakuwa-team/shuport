<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DemoController;
use App\Http\Controllers\Api\V1\EntrySheetController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\LineLinkController;
use App\Http\Controllers\Api\V1\LineWebhookController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ResumeController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API ルート（API定義書 v18 第6章）
|--------------------------------------------------------------------------
|
| 受け口の一覧は API定義書 v18 6.2 が正本。画面のデータの読み書きは
| すべてこの API で行い、Web ルートは画面（Blade）の表示だけに使う
| （2026年11月18日 決定13）。
|
| 認証は Supabase Auth の JWT（Authorization: Bearer）を検証する。
| 認証の基盤は B3、v1 グループへの適用と認証ガードは B4 で行う。
| API-12（LINE Webhook）・API-39（体験用ログイン）・API-40（死活確認）は
| 認証の対象外（API-12 は LINE の署名検証が認証になる）。
|
| 各コントローラは未実装の間、problem+json の 501 を返す。
| API-14〜API-40 のリクエスト・レスポンスの項目は詳細設計書で定める。
|
*/

Route::prefix('v1')->group(function () {
    // ダッシュボード・マイページ・利用者情報
    Route::get('/dashboard', [DashboardController::class, 'show']);              // API-14
    Route::get('/profile', [ProfileController::class, 'show']);                 // API-15
    Route::put('/profile', [ProfileController::class, 'update']);               // API-16
    Route::get('/me', [MeController::class, 'show']);                           // API-35

    // 企業・選考（industry-suggestion は {company_id} より先に定義する）
    Route::post('/companies/industry-suggestion', [CompanyController::class, 'industrySuggestion']); // API-17
    Route::get('/companies', [CompanyController::class, 'index']);              // API-18
    Route::post('/companies', [CompanyController::class, 'store']);             // API-07
    Route::get('/companies/{company_id}', [CompanyController::class, 'show']);  // API-19
    Route::patch('/companies/{company_id}/status', [CompanyController::class, 'updateStatus']); // API-20
    Route::get('/companies/{company_id}/login-info', [CompanyController::class, 'showLoginInfo']); // API-21
    Route::put('/companies/{company_id}/login-info', [CompanyController::class, 'updateLoginInfo']); // API-22
    Route::post('/companies/{company_id}/login-info/password', [CompanyController::class, 'revealPassword']); // API-23
    Route::post('/companies/{company_id}/research', [CompanyController::class, 'research']); // API-09
    Route::post('/companies/{company_id}/topics/refresh', [CompanyController::class, 'refreshTopics']); // API-10

    // ES・履歴書
    Route::post('/entry-sheets', [EntrySheetController::class, 'store']);       // API-08
    Route::get('/entry-sheets/{entry_sheet_id}', [EntrySheetController::class, 'show']); // API-24
    Route::put('/entry-sheets/{entry_sheet_id}', [EntrySheetController::class, 'update']); // API-25
    Route::get('/entry-sheets/{entry_sheet_id}/pdf', [EntrySheetController::class, 'pdf']); // API-26
    Route::post('/entry-sheets/{entry_sheet_id}/generate', [EntrySheetController::class, 'generate']); // API-13
    Route::get('/resume/excel', [ResumeController::class, 'excel']);            // API-27

    // 予定・タスク
    Route::get('/events', [EventController::class, 'index']);                   // API-28
    Route::post('/events', [EventController::class, 'store']);                  // API-29
    Route::put('/events/{event_id}', [EventController::class, 'update']);       // API-30
    Route::delete('/events/{event_id}', [EventController::class, 'destroy']);   // API-31
    Route::get('/tasks', [TaskController::class, 'index']);                     // API-32
    Route::post('/tasks', [TaskController::class, 'store']);                    // API-33
    Route::put('/tasks/{task_id}', [TaskController::class, 'update']);          // API-34

    // ジョブ・LINE・退会
    Route::get('/jobs/{job_id}', [JobController::class, 'show']);               // API-11
    Route::post('/line/webhook', [LineWebhookController::class, 'store']);      // API-12
    Route::post('/line/link-code', [LineLinkController::class, 'store']);       // API-36
    Route::delete('/line/link', [LineLinkController::class, 'destroy']);        // API-37
    Route::delete('/account', [AccountController::class, 'destroy']);           // API-38

    // 認証の対象外
    Route::post('/demo/login', [DemoController::class, 'login']);               // API-39
    Route::get('/health', [HealthController::class, 'show']);                   // API-40
});
