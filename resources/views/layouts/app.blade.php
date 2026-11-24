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
    {{-- 共通レイアウト（基本設計書 v16 4.2）。
         ログイン状態の確認は画面の処理（supabase-js）で行い、未ログインなら
         ログイン画面へ移す（3.4 セキュリティ設計 1-3）。
         利用者のデータは埋め込まず、表示後に API で取得する（決定13）。
         共通ナビに ES作成・編集（No.7）は置かない（決定3）。 --}}
    <div>
        <aside>
            <p>ShuPort</p>
            <nav>
                <ul>
                    <li><a href="{{ route('dashboard') }}">ダッシュボード</a></li>
                    <li><a href="{{ route('companies.index') }}">企業一覧</a></li>
                    <li><a href="{{ route('calendar') }}">カレンダー</a></li>
                    <li><a href="{{ route('tasks.index') }}">タスク</a></li>
                    <li><a href="{{ route('mypage') }}">マイページ</a></li>
                    <li><a href="{{ route('settings') }}">設定</a></li>
                </ul>
            </nav>
        </aside>
        <main>
            @yield('content')
        </main>
    </div>
</body>
</html>
