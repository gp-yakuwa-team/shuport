<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- 画面の supabase-js が使う公開情報（秘密キーは渡さない。基本設計書 v16 6.1） --}}
    <meta name="supabase-url" content="{{ config('supabase.url') }}">
    <meta name="supabase-anon-key" content="{{ config('supabase.anon_key') }}">
    <title>@yield('title', 'ShuPort') - ShuPort</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="@yield('page')">
    {{-- 認証画面用レイアウト（共通ナビなし）。
         ログイン・新規登録・パスワード再設定は、画面から Supabase Auth を
         直接呼ぶ（API定義書 v18 6.2）。 --}}
    <main>
        @yield('content')
    </main>
</body>
</html>
