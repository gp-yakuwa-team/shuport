{{-- M3 タスクの登録・編集モーダル（詳細設計書 第4章）。
     登録 API-33、変更 API-34。処理は resources/js/screens/modals/task-form.js。
     タスク削除の受け口 API は API定義書 v18 に無い（チーム決定待ち）。 --}}
<div id="modal-task-form" hidden>
    <h2>タスク</h2>
    <form id="task-form">
        <div>
            <label for="task-title">タイトル</label>
            <input id="task-title" name="title" type="text" required>
        </div>
        <div>
            <label for="task-due-date">期限（任意）</label>
            <input id="task-due-date" name="due_date" type="date">
        </div>
        <div>
            <label for="task-company">企業（任意）</label>
            <select id="task-company" name="company_id">
                <option value="">企業に紐づけない</option>
            </select>
        </div>
        <button type="submit">保存する</button>
        <button type="button" data-close>閉じる</button>
    </form>
</div>
