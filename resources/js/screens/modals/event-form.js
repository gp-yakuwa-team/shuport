import { api } from '../../lib/api.js';
import { EVENT_TYPES } from '../../lib/constants.js';

// M2 予定の登録・編集モーダル（詳細設計書 第4章）。
// 登録は API-29、変更は API-30、削除は API-31。
export function openEventForm({ event = null, date = null } = {}) {
    const modal = document.getElementById('modal-event-form');
    const form = document.getElementById('event-form');
    modal.hidden = false;

    const typeSelect = document.getElementById('event-type');
    if (typeSelect.options.length === 0) {
        EVENT_TYPES.forEach((t) => typeSelect.add(new Option(t, t)));
    }
    // TODO: 企業の候補（#event-company-list）を API-18 で埋める。
    // 編集のときは event の値を各欄へ入れる。新規のときは date を開始日時の初期値にする。
    form.dataset.eventId = event?.id ?? '';
    document.getElementById('event-delete').hidden = !event;

    form.onsubmit = async (e) => {
        e.preventDefault();
        // TODO: 欄の値を集める。重なり（1分でも重なれば警告、保存はできる）の
        // 確認表示は API の応答に合わせて出す。
        const body = {};
        if (form.dataset.eventId) {
            await api(`/api/v1/events/${form.dataset.eventId}`, { method: 'PUT', body });
        } else {
            await api('/api/v1/events', { method: 'POST', body });
        }
        modal.hidden = true;
        location.reload();
    };
    document.getElementById('event-delete')?.addEventListener('click', async () => {
        await api(`/api/v1/events/${form.dataset.eventId}`, { method: 'DELETE' });
        modal.hidden = true;
        location.reload();
    });
    modal.querySelector('[data-close]')?.addEventListener('click', () => {
        modal.hidden = true;
    });
    void date;
}
