import { supabase } from '../lib/supabase.js';
import { api } from '../lib/api.js';

// No.10 設定（詳細設計書 第4章）。
// メールアドレス・パスワードの変更は Supabase Auth、LINE 連携は API-36・37、退会は API-38。
export default async function init() {
    const me = await api('/api/v1/me');
    if (me?.is_demo) document.getElementById('demo-banner').hidden = false;
    // TODO: #line-status に連携の状態を出す（項目は付録A A.4 の API-35 で確定する）

    document.getElementById('btn-change-email')?.addEventListener('click', () => {
        document.getElementById('email-form').hidden = false;
    });
    document.getElementById('email-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const { error } = await supabase.auth.updateUser(
            { email: e.target.email.value },
            // 確認メールの URL を押した後は、自前の設定画面へ戻す（決定22）。
            { emailRedirectTo: `${location.origin}/settings` },
        );
        document.getElementById('email-notice').hidden = !error ? false : true;
        void error;
    });
    document.getElementById('btn-change-password')?.addEventListener('click', () => {
        document.getElementById('password-form').hidden = false;
    });
    document.getElementById('password-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await supabase.auth.updateUser({ password: e.target.password.value });
    });

    // LINE 連携コードの発行（API-36。有効期限10分・1回だけ使える）
    document.getElementById('btn-link-code')?.addEventListener('click', async () => {
        try {
            const data = await api('/api/v1/line/link-code', { method: 'POST' });
            document.getElementById('link-code').textContent = data?.code ?? '';
            document.getElementById('link-code-display').hidden = false;
        } catch (err) {
            alert(err.message); // 体験用アカウントは発行できない（403）
        }
    });
    document.getElementById('btn-unlink')?.addEventListener('click', async () => {
        await api('/api/v1/line/link', { method: 'DELETE' });
        location.reload();
    });

    // 退会（API-38）。確認のチェックを入れたときだけ押せる
    const agree = document.getElementById('delete-agree');
    const deleteBtn = document.getElementById('btn-delete-account');
    agree?.addEventListener('change', () => {
        deleteBtn.disabled = !agree.checked;
    });
    deleteBtn?.addEventListener('click', async () => {
        await api('/api/v1/account', { method: 'DELETE' });
        await supabase.auth.signOut({ scope: 'local' });
        location.href = '/login';
    });
}
