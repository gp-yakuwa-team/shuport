import { supabase } from './supabase.js';

// REST API の共通呼び出し（決定13：認証は Authorization: Bearer）。
// - アクセストークンは supabase-js のセッションから取る
// - 成功時は data の中身を返す（204 のときは null）
// - 失敗時は problem+json（RFC 9457）を読み、status・detail・errors を持つ Error を投げる
export async function api(path, { method = 'GET', body, signal } = {}) {
    const { data } = supabase ? await supabase.auth.getSession() : { data: {} };
    const token = data.session?.access_token;

    const res = await fetch(path, {
        method,
        ...(signal ? { signal } : {}),
        headers: {
            Accept: 'application/json',
            ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    });
    recordActivity();

    if (res.status === 204) return null;
    const json = await res.json().catch(() => null);
    if (!res.ok) {
        const err = new Error(json?.detail || json?.title || `HTTP ${res.status}`);
        err.status = res.status;
        err.errors = json?.errors;
        err.body = json; // 409 の company_id など、追加の項目が要る画面のため
        throw err;
    }
    return json?.data ?? json;
}

// ファイルを返す API（PDF・Excel）用。アクセストークン付きで取得し、Blob を返す。
export async function apiFile(path) {
    const { data } = supabase ? await supabase.auth.getSession() : { data: {} };
    const token = data.session?.access_token;
    const res = await fetch(path, {
        headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    });
    recordActivity();
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.blob();
}

// 最後の操作時刻（無操作サインアウトの判定用。タブ間で共有する）
const ACTIVITY_KEY = 'shuport:last-activity';

export function recordActivity() {
    try {
        localStorage.setItem(ACTIVITY_KEY, String(Date.now()));
    } catch {
        // 保存できない環境では何もしない
    }
}

export function lastActivity() {
    try {
        return Number(localStorage.getItem(ACTIVITY_KEY)) || Date.now();
    } catch {
        return Date.now();
    }
}
