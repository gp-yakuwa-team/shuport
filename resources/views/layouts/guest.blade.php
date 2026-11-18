<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ShuPort') - ShuPort</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{-- 認証画面用レイアウト（共通ナビなし）。
         ログイン・新規登録・パスワード再設定は、画面から Supabase Auth を
         直接呼ぶ（API定義書 v18 6.2）。 --}}
    <main>
        @yield('content')
    </main>
</body>
</html>
