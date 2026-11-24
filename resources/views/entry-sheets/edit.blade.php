@extends('layouts.app')

@section('title', 'ES作成・編集')
@section('page', 'entry-sheet')

@section('content')
    <h1>ES作成・編集</h1>
    {{-- No.7（詳細設計書 第4章）。取得 API-24、保存 API-25、AI生成 API-13、
         PDF API-26、Excel API-27。処理は resources/js/screens/entry-sheet.js --}}
    <p>企業：<span id="es-company"></span></p>
    <div>
        <label for="es-title">ES の名前</label>
        <input id="es-title" type="text">
    </div>
    <div id="generate-progress" hidden>
        <p>AI が初案を作成しています…</p>
    </div>
    <div id="question-list"></div>
    <div>
        <button id="btn-generate" type="button">AIで初案を作成</button>
        <button id="btn-save" type="button">保存する</button>
        <button id="btn-pdf" type="button">PDFで出力</button>
        <button id="btn-excel" type="button">Excelで出力</button>
    </div>
@endsection
