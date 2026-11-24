import { api } from '../lib/api.js';
import { openEventForm } from './modals/event-form.js';

// No.8 カレンダー（詳細設計書 第4章）。予定の取得は API-28。
export default async function init() {
    let cursor = new Date();
    cursor.setDate(1);

    async function load() {
        const from = toDate(cursor);
        const to = toDate(new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0));
        document.getElementById('calendar-title').textContent =
            `${cursor.getFullYear()}年${cursor.getMonth() + 1}月`;
        const data = await api(`/api/v1/events?from=${from}&to=${to}`);
        // TODO: #calendar-grid へ月表示を描き、#event-list へその月の予定を並べる。
        // 日を選ぶと openEventForm({ date })、予定を選ぶと openEventForm({ event }) を呼ぶ。
        void data;
    }

    document.getElementById('btn-prev-month')?.addEventListener('click', () => {
        cursor.setMonth(cursor.getMonth() - 1);
        load();
    });
    document.getElementById('btn-next-month')?.addEventListener('click', () => {
        cursor.setMonth(cursor.getMonth() + 1);
        load();
    });
    document.getElementById('btn-add-event')?.addEventListener('click', () =>
        openEventForm({ date: toDate(new Date()) }),
    );
    await load();
}

function toDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
