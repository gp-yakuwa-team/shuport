<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $dbConnected = false;
    $dbName = '';

    try {
        // Supabase(DB) への疎通確認
        DB::connection()->getPdo();
        $dbConnected = true;
        $dbName = DB::connection()->getDatabaseName();
    } catch (Exception $e) {
        $dbConnected = false;
    }

    return view('welcome', [
        'dbConnected' => $dbConnected,
        'dbName' => $dbName,
    ]);
});

/*
|--------------------------------------------------------------------------
| 画面ルート（基本設計書 v16 4.1 の12画面）
|--------------------------------------------------------------------------
|
| Web ルートは画面（Blade）の表示だけに使う（決定13）。データの読み書きは
| すべて API（routes/api.php）で行う。保護対象画面のログイン確認は、
| 画面の処理（supabase-js）で行う（3.4 セキュリティ設計 1-3）。
| パスは暫定（詳細設計書で確定する）。
|
*/

// 認証（独立画面）
Route::view('/login', 'auth.login')->name('login');                                    // No.1
Route::view('/register', 'auth.register')->name('register');                           // No.2
Route::view('/password/reset-request', 'auth.forgot-password')->name('password.request'); // No.11
Route::view('/password/reset', 'auth.reset-password')->name('password.reset');         // No.12

// ログイン後の画面（共通レイアウト）
Route::view('/dashboard', 'dashboard')->name('dashboard');                             // No.3
Route::view('/mypage', 'mypage')->name('mypage');                                       // No.4
Route::view('/companies', 'companies.index')->name('companies.index');                 // No.5
Route::view('/companies/create', 'companies.create')->name('companies.create');        // No.6
Route::view('/entry-sheets/{entry_sheet_id}', 'entry-sheets.edit')->name('entry-sheets.edit'); // No.7
Route::view('/calendar', 'calendar')->name('calendar');                                // No.8
Route::view('/tasks', 'tasks.index')->name('tasks.index');                             // No.9
Route::view('/settings', 'settings')->name('settings');                                // No.10
