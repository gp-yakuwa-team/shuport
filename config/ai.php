<?php

return [

    /*
    |--------------------------------------------------------------------------
    | メインAI（Gemini）
    |--------------------------------------------------------------------------
    |
    | モデルは GEMINI_MODEL で切り替える（決定2。コードに固定しない）。
    | 本番の呼び出しは、利用規約（18歳以上）の適合確認が済むまで有効化しない
    | （決定12）。AI_GEMINI_PAID_PROJECT_VERIFIED が true で、課金プロジェクト
    | での確認が取れている場合だけ GeminiApiService が呼び出しを行う。
    |
    */

    /*
    |--------------------------------------------------------------------------
    | プロンプト本文の配置（resources/prompts）
    |--------------------------------------------------------------------------
    |
    | プロンプトの本文は 4.11 AIプロンプト設計詳細設計書が正本で、
    | 確定した本文だけを resources/prompts/{番号}/v{版}/ に置く。
    | ここには、そのファイルへの相対パスを設定する。未配置の間は null で、
    | その機能は候補を返さないなど、AI なしでも続けられる形にする。
    |
    */

    'prompts' => [
        'industry' => env('AI_PROMPT_INDUSTRY_PATH'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'paid_project_verified' => (bool) env('AI_GEMINI_PAID_PROJECT_VERIFIED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | サブAI（Cloudflare Workers AI）
    |--------------------------------------------------------------------------
    |
    | メインAIが使えないときの切り替え先。切り替えの対象は ES生成・業界の候補・
    | LINEのトーク解析だけで、企業研究と最新トピックス再取得は切り替えない
    | （API定義書 v18 第3章）。無料枠は1日10,000 neurons。
    |
    */

    'workers_ai' => [
        'account_id' => env('CF_ACCOUNT_ID'),
        'api_token' => env('CF_AI_TOKEN'),
        'model' => env('WORKERS_AI_MODEL', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'),
    ],

];
