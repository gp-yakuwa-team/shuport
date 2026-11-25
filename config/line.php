<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LINE Messaging API
    |--------------------------------------------------------------------------
    |
    | チャネルシークレットは Webhook の署名検証（VerifyLineSignature）に、
    | チャネルアクセストークンはメッセージ送信（LineApiService）に使う。
    | 実キーの発行は A2（Issue #34）で、値は Render の環境変数に置く。
    |
    */

    'channel_secret' => env('LINE_CHANNEL_SECRET'),
    'channel_access_token' => env('LINE_CHANNEL_ACCESS_TOKEN'),

    /*
    | 連携コード（6桁）のハッシュ用 pepper（決定18）。
    | code_hash は HMAC-SHA256（鍵 = この値）で作る。鍵を変えると発行済みの
    | コードはすべて無効になる。本番では必ず LINE_CODE_PEPPER を設定すること
    | （未設定の間は APP_KEY で代替する）。
    */

    'link_code_pepper' => env('LINE_CODE_PEPPER'),

    /*
    | 定時Push の月の上限（通）。達した月は送信を止め、翌月1日に再開する。
    | 送信数と上限の確認は LINE の API で行う（LineApiService）。
    */

    'monthly_push_limit' => 200,

    /*
    |--------------------------------------------------------------------------
    | トーク解析（BG-04）
    |--------------------------------------------------------------------------
    |
    | 本文と replyToken は暗号化して cache に置き、ParseLineMessageJob が
    | 取り出して消す。replyToken の有効期限（1分）を考え、受信から55秒を
    | 過ぎたジョブは解析も返信もしない（4.10 3.4）。
    |
    */

    'message_cache_ttl_seconds' => 120,

    'reply_token_budget_seconds' => 55,

    /*
    | 同じ LINE アカウントからの連携コードの誤入力は、10分に5回まで。
    */
    'code_attempt_limit' => 5,

    'code_attempt_decay_seconds' => 600,

];
