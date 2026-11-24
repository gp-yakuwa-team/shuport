@extends('layouts.app')

@section('title', 'ダッシュボード')
@section('page', 'dashboard')

@section('content')
    <h1>ダッシュボード</h1>
    {{-- No.3（詳細設計書 第4章）。データは API-14・API-35 で取得し、
         resources/js/screens/dashboard.js が各セクションへ描く（決定13） --}}
    <p id="load-error" hidden>読み込みに失敗しました。時間をおいて再度開いてください。</p>
    <div id="line-suspension-banner" hidden>
        <p>LINE の定時配信は、月の上限に達したため休止中です。</p>
    </div>
    <section id="upcoming-events">
        <h2>直近7日間の予定</h2>
    </section>
    <section id="near-deadlines">
        <h2>期限が近いES（3日以内）</h2>
    </section>
    <section id="status-counts">
        <h2>選考ステータス別の企業数</h2>
    </section>
    <section id="open-tasks">
        <h2>未完了タスク</h2>
    </section>
@endsection
