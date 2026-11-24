import { supabase } from '../lib/supabase.js';

// No.11 パスワード再設定の依頼（詳細設計書 第4章）。
// Supabase Auth のメール送信を使う。登録の有無は明かさない。
export default function init() {
    const form = document.getElementById('forgot-form');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await supabase.auth.resetPasswordForEmail(form.email.value, {
            redirectTo: `${location.origin}/password/reset`,
        });
        form.hidden = true;
        document.getElementById('forgot-complete').hidden = false;
    });
}
