@extends('layouts.app')

@section('title', '設定')
@section('page', 'settings')

@section('content')
    <h1>設定</h1>
    {{-- No.10（詳細設計書 第4章）。処理は resources/js/screens/settings.js --}}
    <div id="demo-banner" hidden>
        <p>体験用アカウントでログイン中です。この画面の一部は使えません。</p>
    </div>

    <section>
        <h2>アカウント</h2>
        <button id="btn-change-email" type="button">メールアドレスを変更</button>
        <form id="email-form" hidden>
            <label for="email">新しいメールアドレス</label>
            <input id="email" name="email" type="email" required autocomplete="email">
            <button type="submit">変更する</button>
            <p id="email-notice" hidden>確認メールを送りました。</p>
        </form>
        <button id="btn-change-password" type="button">パスワードを変更</button>
        <form id="password-form" hidden>
            <label for="password">新しいパスワード</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            <button type="submit">変更する</button>
        </form>
    </section>

    <section>
        <h2>LINE連携</h2>
        <p id="line-status"></p>
        <button id="btn-link-code" type="button">連携コードを発行</button>
        <div id="link-code-display" hidden>
            <p>連携コード：<strong id="link-code"></strong>（10分間有効）</p>
            <p>ShuPort の LINE 公式アカウントに、このコードを送ってください。</p>
        </div>
        <button id="btn-unlink" type="button">連携を解除</button>
    </section>

    <section>
        <h2>規約</h2>
        {{-- TODO: 規約・プライバシーポリシーの本文は確定後にここへ入れる --}}
        <div id="terms-body"></div>
    </section>

    <section>
        <h2>退会</h2>
        <p>退会すると、登録した企業・ES・予定・タスクはすべて削除され、元に戻せません。</p>
        <label>
            <input type="checkbox" id="delete-agree">
            内容を理解しました
        </label>
        <button id="btn-delete-account" type="button" disabled>退会する</button>
    </section>
@endsection
