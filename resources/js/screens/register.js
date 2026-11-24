import { supabase } from '../lib/supabase.js';

// No.2 新規登録（詳細設計書 第4章）。
// 登録は Supabase Auth を直接呼ぶ（signUp）。利用者の行は DB のトリガーが作る。
// 年齢確認のチェックは設けない（決定12）。
export default function init() {
    const form = document.getElementById('register-form');
    const errorBox = document.getElementById('form-error');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorBox.hidden = true;
        if (!form.agree.checked) {
            errorBox.textContent = '利用規約とプライバシーポリシーへの同意が必要です';
            errorBox.hidden = false;
            return;
        }
        const { error } = await supabase.auth.signUp({
            email: form.email.value,
            password: form.password.value,
        });
        if (error) {
            errorBox.textContent = '登録できませんでした。入力内容を確認してください';
            errorBox.hidden = false;
            return;
        }
        // TODO: 同意の記録（terms_accepted_at・terms_version）の付け方は、
        // 付録A A.4（API-15・16）の確定に合わせて実装する
        form.hidden = true;
        document.getElementById('register-complete').hidden = false;
    });
}
