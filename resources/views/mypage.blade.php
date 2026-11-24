@extends('layouts.app')

@section('title', 'マイページ')
@section('page', 'mypage')

@section('content')
    <h1>マイページ</h1>
    {{-- No.4（詳細設計書 第4章）。取得は API-15、保存は API-16。
         入力欄の id と API の項目名の対応は付録A A.4 で確定する。
         処理は resources/js/screens/mypage.js --}}
    <p id="load-error" hidden>読み込みに失敗しました。時間をおいて再度開いてください。</p>
    <form id="profile-form">
        <section>
            <h2>学歴</h2>
            <div id="educations"></div>
            <button type="button" id="btn-add-education">学歴を追加</button>
        </section>
        <section>
            <h2>自己PR・ガクチカ・長所・短所</h2>
            <div>
                <label for="self-pr">自己PR</label>
                <textarea id="self-pr" rows="6"></textarea>
            </div>
            <div>
                <label for="gakuchika">学生時代に力を入れたこと（ガクチカ）</label>
                <textarea id="gakuchika" rows="6"></textarea>
            </div>
            <div>
                <label for="strengths">長所</label>
                <textarea id="strengths" rows="4"></textarea>
            </div>
            <div>
                <label for="weaknesses">短所</label>
                <textarea id="weaknesses" rows="4"></textarea>
            </div>
        </section>
        <section>
            <h2>資格</h2>
            <div id="qualifications"></div>
            <button type="button" id="btn-add-qualification">資格を追加</button>
        </section>
        <section>
            <h2>任意項目</h2>
            <div>
                <label for="academic-other">学業以外で力を入れたこと</label>
                <textarea id="academic-other" rows="4"></textarea>
            </div>
            <div>
                <label for="club">クラブ・サークル</label>
                <textarea id="club" rows="4"></textarea>
            </div>
            <div>
                <label for="hobby">趣味・特技</label>
                <textarea id="hobby" rows="4"></textarea>
            </div>
        </section>
        <section>
            <h2>リンク</h2>
            <div id="links"></div>
            <button type="button" id="btn-add-link">リンクを追加</button>
        </section>
        <section>
            <h2>自由記入</h2>
            <div id="free-entries"></div>
            <button type="button" id="btn-add-free">自由記入を追加</button>
        </section>
        <button type="submit">保存する</button>
        <p id="save-complete" hidden>保存しました。</p>
    </form>
@endsection
