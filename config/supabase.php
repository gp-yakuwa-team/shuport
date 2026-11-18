<?php

return [

    // Supabase プロジェクトの URL（https://<プロジェクト ref>.supabase.co）
    'url' => env('SUPABASE_URL'),

    // 公開キー。画面の supabase-js がログインとトークンの管理に使う
    'anon_key' => env('SUPABASE_ANON_KEY'),

    // シークレットキー（sb_secret_ で始まるもの）。
    // サーバー側でのみ使用し、ブラウザには渡さない（基本設計書 v16 6.1）
    'secret_key' => env('SUPABASE_SECRET_KEY'),

    // 旧式の対称鍵（HS256）で署名するプロジェクト用の予備。
    // 通常は JWKS（公開鍵）で検証するため、空でよい
    'jwt_secret' => env('SUPABASE_JWT_SECRET'),

    // 署名検証用の公開鍵（JWKS）をキャッシュする秒数
    'jwks_cache_ttl' => (int) env('SUPABASE_JWKS_CACHE_TTL', 600),

];
