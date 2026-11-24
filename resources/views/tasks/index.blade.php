@extends('layouts.app')

@section('title', 'タスク')
@section('page', 'tasks')

@section('content')
    <h1>タスク</h1>
    {{-- No.9（詳細設計書 第4章）。一覧は API-32。処理は resources/js/screens/tasks.js --}}
    <div>
        <button type="button" data-filter="open">未完了</button>
        <button type="button" data-filter="done">完了</button>
        <button type="button" data-filter="all">すべて</button>
        <button id="btn-add-task" type="button">タスクを追加</button>
    </div>
    <ul id="task-list"></ul>

    @include('components.modals.task-form')
@endsection
