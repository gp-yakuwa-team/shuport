@extends('layouts.guest')

@section('title', 'ログイン')
@section('page', 'login')

@section('content')
    <h1>ログイン</h1>
    {{-- No.1 ログイン（詳細設計書 第4章）。処理は resources/js/screens/login.js --}}
    <p id="login-notice" hidden></p>
    <p id="form-error" hidden></p>
    <form id="login-form">
        <div>
            <label for="email">メールアドレス</label>
            <input id="email" name="email" type="email" required autocomplete="email">
        </div>
        <div>
            <label for="password">パスワード</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button type="submit">ログイン</button>
    </form>
    <p><a href="{{ route('password.request') }}">パスワードを忘れた方</a></p>
    <p><a href="{{ route('register') }}">新規登録はこちら</a></p>

    {{-- 体験用ログインは URL に demo=1 があるときだけ出す（公開期間中だけ有効） --}}
    <div id="demo-login" hidden>
        <h2>体験用ログイン</h2>
        <p>登録なしで、サンプルのデータで試せます。</p>
        <button id="demo-login-button" type="button">体験用アカウントでログイン</button>
    </div>
@endsection
