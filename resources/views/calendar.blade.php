@extends('layouts.app')

@section('title', 'カレンダー')
@section('page', 'calendar')

@section('content')
    <h1>カレンダー</h1>
    {{-- No.8（詳細設計書 第4章）。予定の取得は API-28。処理は resources/js/screens/calendar.js --}}
    <div>
        <button id="btn-prev-month" type="button">前の月</button>
        <span id="calendar-title"></span>
        <button id="btn-next-month" type="button">次の月</button>
        <button id="btn-add-event" type="button">予定を追加</button>
    </div>
    <div id="calendar-grid"></div>
    <section>
        <h2>この月の予定</h2>
        <ul id="event-list"></ul>
    </section>

    @include('components.modals.event-form')
@endsection
