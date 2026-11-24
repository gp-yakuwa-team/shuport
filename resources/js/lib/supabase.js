import { createClient } from '@supabase/supabase-js';

// Supabase のクライアント（画面用）。
// URL と公開キー（anon key）は、レイアウトが出力する meta タグから読む。
// 秘密キー（secret key）はブラウザに渡さない（基本設計書 v16 6.1）。
// 環境変数が未設定の環境では null になり、認証を使う画面は動作しない。
const url = document.querySelector('meta[name="supabase-url"]')?.content;
const anonKey = document.querySelector('meta[name="supabase-anon-key"]')?.content;

export const supabase = url && anonKey ? createClient(url, anonKey) : null;
