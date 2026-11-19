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
    | 定時Push の月の上限（通）。達した月は送信を止め、翌月1日に再開する。
    | 送信数と上限の確認は LINE の API で行う（LineApiService）。
    */

    'monthly_push_limit' => 200,

];
