import { supabase } from './supabase.js';
import { recordActivity, lastActivity } from './api.js';

// 無操作サインアウトの制限時間（3.4 セキュリティ設計 1-8：120分）
const INACTIVITY_LIMIT = 120 * 60 * 1000;

// ログイン後の画面用。セッションが無ければログイン画面へ移す。
export async function requireSession() {
    if (!supabase) return null;
    const { data } = await supabase.auth.getSession();
    if (!data.session) {
        location.href = '/login';
        return null;
    }
    return data.session;
}

// 無操作120分で、画面側だけでサインアウトする（3.4 1-8）。
// Supabase のセッション設定（Inactivity timeout）は使わない（決定・8.6/8.1）。
// 操作中は supabase-js がトークンを自動更新するので、入力の途中で切れることはない。
export function startInactivityWatch() {
    if (!supabase) return;
    recordActivity();
    ['click', 'keydown'].forEach((ev) =>
        document.addEventListener(ev, recordActivity, { passive: true }),
    );
    setInterval(async () => {
        if (Date.now() - lastActivity() >= INACTIVITY_LIMIT) {
            await supabase.auth.signOut({ scope: 'local' });
            location.href = '/login?reason=inactive';
        }
    }, 60 * 1000);
}
