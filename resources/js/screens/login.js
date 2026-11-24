import { supabase } from '../lib/supabase.js';
import { api } from '../lib/api.js';

// No.1 ログイン（詳細設計書 第4章）。
// ログインは Supabase Auth を直接呼ぶ（API定義書 v18 6.2）。ShuPort の API は呼ばない。
export default function init() {
    const params = new URLSearchParams(location.search);
    const notice = document.getElementById('login-notice');
    if (notice && params.get('reason') === 'inactive') {
        notice.textContent = 'しばらく操作がなかったため、ログアウトしました';
        notice.hidden = false;
    }
    if (notice && params.get('reason') === 'password-changed') {
        notice.textContent = 'パスワードを変更しました。新しいパスワードでログインしてください';
        notice.hidden = false;
    }

    // 体験用ログインの入口は、URL に demo=1 があるときだけ出す（公開期間中だけ有効）
    if (params.get('demo') === '1') {
        document.getElementById('demo-login').hidden = false;
    }

    const form = document.getElementById('login-form');
    const errorBox = document.getElementById('form-error');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorBox.hidden = true;
        const { error } = await supabase.auth.signInWithPassword({
            email: form.email.value,
            password: form.password.value,
        });
        if (error) {
            // どの項目が違うかは明かさない
            errorBox.textContent = 'メールアドレスまたはパスワードが正しくありません';
            errorBox.hidden = false;
            return;
        }
        location.href = '/dashboard';
    });

    document.getElementById('demo-login-button')?.addEventListener('click', async () => {
        errorBox.hidden = true;
        try {
            const data = await api('/api/v1/demo/login', { method: 'POST' });
            await supabase.auth.setSession({
                access_token: data.access_token,
                refresh_token: data.refresh_token,
            });
            location.href = '/dashboard';
        } catch {
            errorBox.textContent = '体験用ログインは今は利用できません';
            errorBox.hidden = false;
        }
    });
}
