import { api } from '../lib/api.js';

// No.4 マイページ（詳細設計書 第4章）。取得は API-15、保存は API-16。
// 入力欄の id と API の項目名の対応は、付録A A.4（API-15・16）の確定に合わせてそろえる。
export default async function init() {
    const form = document.getElementById('profile-form');
    try {
        const profile = await api('/api/v1/profile');
        // TODO: 取得した値を各入力欄へ入れる（学歴は #educations へ行を追加する）
        void profile;
    } catch {
        document.getElementById('load-error').hidden = false;
        return;
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        // TODO: 入力欄の値を集めて送る
        await api('/api/v1/profile', { method: 'PUT', body: {} });
        document.getElementById('save-complete').hidden = false;
    });
}
