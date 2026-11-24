{{-- M2 予定の登録・編集モーダル（詳細設計書 第4章）。
     登録 API-29、変更 API-30、削除 API-31。処理は resources/js/screens/modals/event-form.js --}}
<div id="modal-event-form" hidden>
    <h2>予定</h2>
    <form id="event-form">
        <div>
            <label for="event-title">タイトル</label>
            <input id="event-title" name="title" type="text" required>
        </div>
        <div>
            <label for="event-type">種別</label>
            <select id="event-type" name="event_type"></select>
        </div>
        <div>
            <label for="event-company">企業（任意）</label>
            <input id="event-company" name="company" type="text" list="event-company-list">
            <datalist id="event-company-list"></datalist>
        </div>
        <div>
            <label for="event-starts-at">開始</label>
            <input id="event-starts-at" name="starts_at" type="datetime-local" required>
        </div>
        <div>
            <label for="event-ends-at">終了</label>
            <input id="event-ends-at" name="ends_at" type="datetime-local">
        </div>
        <div>
            <label for="event-meeting-url">面接のURL（任意）</label>
            <input id="event-meeting-url" name="meeting_url" type="url">
        </div>
        <div>
            <label for="event-memo">メモ（任意）</label>
            <textarea id="event-memo" name="memo" rows="3"></textarea>
        </div>
        <button type="submit">保存する</button>
        <button id="event-delete" type="button" hidden>削除する</button>
        <button type="button" data-close>閉じる</button>
    </form>
</div>
