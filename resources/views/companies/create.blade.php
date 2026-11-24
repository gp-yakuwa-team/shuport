@extends('layouts.app')

@section('title', '企業を追加')
@section('page', 'company-create')

@section('content')
    <h1>企業を追加</h1>
    {{-- No.6（詳細設計書 第4章）。業界の候補は API-17、登録は API-07。
         登録すると企業研究が始まる。処理は resources/js/screens/company-create.js --}}
    <form id="company-form">
        <div>
            <label for="company-name">企業名</label>
            <input id="company-name" name="company_name" type="text" required>
        </div>
        <div>
            <label for="industry">業界</label>
            <select id="industry" name="industry" required></select>
        </div>
        <div>
            <label for="job-info-text">求人情報（任意）</label>
            <textarea id="job-info-text" name="job_info_text" rows="8"></textarea>
        </div>
        <p id="research-limit-notice" hidden>
            企業研究は本日の上限に達したため、研究は行わずに登録しました。
        </p>
        <button type="submit" name="action" value="register">登録</button>
        <button type="submit" name="action" value="register-and-es">登録してESを作成</button>
    </form>
@endsection
