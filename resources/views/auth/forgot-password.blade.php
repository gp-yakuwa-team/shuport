@extends('layouts.guest')

@section('title', 'パスワード再設定の依頼')
@section('page', 'forgot-password')

@section('content')
    <h1>パスワード再設定の依頼</h1>
    {{-- No.11（詳細設計書 第4章）。処理は resources/js/screens/forgot-password.js --}}
    <form id="forgot-form">
        <div>
            <label for="email">メールアドレス</label>
            <input id="email" name="email" type="email" required autocomplete="email">
        </div>
        <button type="submit">再設定メールを送る</button>
    </form>
    <div id="forgot-complete" hidden>
        <p>入力されたメールアドレスに、再設定用のメールを送りました（登録がある場合）。</p>
        <p><a href="{{ route('login') }}">ログイン画面へ</a></p>
    </div>
@endsection
