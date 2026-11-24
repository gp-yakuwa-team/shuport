@extends('layouts.app')

@section('title', '企業一覧')
@section('page', 'companies')

@section('content')
    <h1>企業一覧</h1>
    {{-- No.5（詳細設計書 第4章）。一覧は API-18。処理は resources/js/screens/companies.js --}}
    <div>
        <input id="company-search" type="search" placeholder="企業名で検索">
        <select id="status-filter">
            <option value="">すべての状態</option>
        </select>
        <a href="{{ route('companies.create') }}">企業を追加</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>企業名</th>
                <th>業界</th>
                <th>選考ステータス</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="company-rows"></tbody>
    </table>

    @include('components.modals.company-detail')
@endsection
