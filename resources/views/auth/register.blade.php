@extends('layouts.guest')

@section('title', '新規登録')
@section('page', 'register')

@section('content')
    <h1>新規登録</h1>
    {{-- No.2 新規登録（詳細設計書 第4章）。処理は resources/js/screens/register.js。
         年齢確認のチェックは設けない（決定12）。 --}}
    <p id="form-error" hidden></p>
    <form id="register-form">
        <div>
            <label for="email">メールアドレス</label>
            <input id="email" name="email" type="email" required autocomplete="email">
        </div>
        <div>
            <label for="password">パスワード</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            <p>8文字以上で、英大文字・英小文字・数字をすべて含めてください。</p>
        </div>
        <div>
            <h2>利用規約</h2>
            {{-- TODO: 規約の本文は確定後にここへ入れる（（仮）の間は枠だけ） --}}
            <div id="terms-body"></div>
        </div>
        <div>
            <h2>プライバシーポリシー</h2>
            <div id="privacy-body"></div>
        </div>
        <label>
            <input type="checkbox" name="agree" required>
            利用規約とプライバシーポリシーに同意します
        </label>
        <button type="submit">登録する</button>
    </form>
    <div id="register-complete" hidden>
        <p>確認メールを送りました。メール内のリンクから登録を完了してください。</p>
        <p><a href="{{ route('login') }}">ログイン画面へ</a></p>
    </div>
@endsection
