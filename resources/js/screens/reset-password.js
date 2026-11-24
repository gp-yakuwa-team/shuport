import { supabase } from '../lib/supabase.js';

// No.12 パスワード再設定（詳細設計書 第4章）。
// メールのリンクから来ると、Supabase が復旧用のセッションを自動で作る。
export default function init() {
    const form = document.getElementById('reset-form');
    const errorBox = document.getElementById('form-error');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorBox.hidden = true;
        if (form.password.value !== form.password_confirmation.value) {
            errorBox.textContent = '新しいパスワードが一致しません';
            errorBox.hidden = false;
            return;
        }
        const { error } = await supabase.auth.updateUser({ password: form.password.value });
        if (error) {
            errorBox.textContent = 'パスワードを変更できませんでした。リンクの有効期限が切れている可能性があります';
            errorBox.hidden = false;
            return;
        }
        await supabase.auth.signOut({ scope: 'local' });
        location.href = '/login?reason=password-changed';
    });
}
