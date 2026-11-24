import { api } from '../lib/api.js';
import { openTaskForm } from './modals/task-form.js';

// No.9 タスク（詳細設計書 第4章）。一覧は API-32、変更は API-34。
export default async function init() {
    const list = document.getElementById('task-list');
    let filter = 'open'; // open / done / all

    async function load() {
        const data = await api(`/api/v1/tasks?filter=${filter}`);
        list.replaceChildren();
        // TODO: 1行（完了チェック・タイトル・期限・企業名）を描く。
        // 行を選ぶと openTaskForm({ task }) を呼ぶこと。
        void data;
    }

    list.addEventListener('change', async (e) => {
        const checkbox = e.target.closest('[data-toggle]');
        if (!checkbox) return;
        await api(`/api/v1/tasks/${checkbox.dataset.toggle}`, {
            method: 'PUT',
            body: { is_completed: checkbox.checked },
        });
        await load();
    });
    document.querySelectorAll('[data-filter]').forEach((btn) =>
        btn.addEventListener('click', () => {
            filter = btn.dataset.filter;
            load();
        }),
    );
    document.getElementById('btn-add-task')?.addEventListener('click', () => openTaskForm({}));
    await load();
}
