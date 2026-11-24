import { api } from '../../lib/api.js';

// M3 タスクの登録・編集モーダル（詳細設計書 第4章）。
// 登録は API-33、変更は API-34。
// 注意：タスク削除の受け口 API は API定義書 v18 に無い（チーム決定待ち）。
export function openTaskForm({ task = null, companyId = null } = {}) {
    const modal = document.getElementById('modal-task-form');
    const form = document.getElementById('task-form');
    modal.hidden = false;

    // TODO: 企業の候補（#task-company）を API-18 で埋める。
    // 編集のときは task の値を各欄へ入れる。
    form.dataset.taskId = task?.id ?? '';
    form.dataset.companyId = companyId ?? '';

    form.onsubmit = async (e) => {
        e.preventDefault();
        // TODO: 欄の値を集める
        const body = {};
        if (form.dataset.taskId) {
            await api(`/api/v1/tasks/${form.dataset.taskId}`, { method: 'PUT', body });
        } else {
            await api('/api/v1/tasks', { method: 'POST', body });
        }
        modal.hidden = true;
        location.reload();
    };
    modal.querySelector('[data-close]')?.addEventListener('click', () => {
        modal.hidden = true;
    });
}
