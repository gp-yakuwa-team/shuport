import { api } from '../lib/api.js';

// No.3 ダッシュボード（詳細設計書 第4章）。データは API-14・API-35 で取る（決定13）。
export default async function init() {
    try {
        const me = await api('/api/v1/me');
        // TODO: 定時Push の配信休止中は #line-suspension-banner を出す
        // （判定に使う項目は付録A A.4 の API-35 で確定する）
        void me;

        const data = await api('/api/v1/dashboard');
        // TODO: 次の要素へ描く（項目は付録A A.4 の API-14 で確定する）
        //   #upcoming-events … 直近7日間の予定
        //   #near-deadlines  … 期限が近いES（3日以内）
        //   #status-counts   … 選考ステータス別の企業数
        //   #open-tasks      … 未完了タスク
        void data;
    } catch {
        document.getElementById('load-error').hidden = false;
    }
}
