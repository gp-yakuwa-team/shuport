@extends('layouts.guest')

@section('title', 'パスワード再設定')
@section('page', 'reset-password')

@section('content')
    <h1>パスワード再設定</h1>
    {{-- No.12（詳細設計書 第4章）。処理は resources/js/screens/reset-password.js --}}
    <p id="form-error" hidden></p>
    <form id="reset-form">
        <div>
            <label for="password">新しいパスワード</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            <p>8文字以上で、英大文字・英小文字・数字をすべて含めてください。</p>
        </div>
        <div>
            <label for="password-confirmation">新しいパスワード（確認）</label>
            <input id="password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <button type="submit">パスワードを変更する</button>
    </form>
@endsection
