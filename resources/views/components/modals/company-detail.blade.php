{{-- M1 企業詳細モーダル（基本設計書 v16 4.4・詳細設計書 第4章）。
     ①企業情報・③選考の履歴・④AI企業研究のタブと、②採用サイトのログイン情報。
     中身は API-19・21 で取得する（決定13）。処理は resources/js/screens/modals/company-detail.js --}}
<div id="modal-company-detail" hidden>
    <div>
        <h2><span id="m1-company-name"></span></h2>
        <p><span id="m1-industry"></span></p>
        <label>
            選考ステータス
            <select id="m1-status"></select>
        </label>
        <button type="button" data-close>閉じる</button>
    </div>
    <div>
        <button type="button" data-tab="research">AI企業研究</button>
        <button type="button" data-tab="login">採用サイトのログイン情報</button>
        <button type="button" data-tab="es">この企業のES</button>
        <button type="button" data-tab="job">求人情報</button>
    </div>
    <section id="m1-tab-research">
        <div id="m1-research"></div>
        <button id="m1-btn-research" type="button">企業研究を実行</button>
        <button id="m1-btn-topics" type="button">トピックスを再取得</button>
    </section>
    <section id="m1-tab-login" hidden>
        <div>
            <label for="m1-login-url">採用サイトのURL</label>
            <input id="m1-login-url" type="url">
        </div>
        <div>
            <label for="m1-login-id">ログインID</label>
            <input id="m1-login-id" type="text">
        </div>
        <div>
            <label for="m1-login-password">パスワード</label>
            <input id="m1-login-password" type="password">
            <button id="m1-btn-reveal" type="button">表示する</button>
        </div>
        <button id="m1-btn-save-login" type="button">保存する</button>
    </section>
    <section id="m1-tab-es" hidden>
        <ul id="m1-es-list"></ul>
    </section>
    <section id="m1-tab-job" hidden>
        <div id="m1-job-info"></div>
    </section>
    <div>
        <button id="m1-btn-delete" type="button">企業を削除</button>
    </div>
</div>
