import { api } from '../../lib/api.js';
import { SELECTION_STATUSES } from '../../lib/constants.js';

// M1 企業詳細モーダル（詳細設計書 第4章）。
// 取得は API-19、ログイン情報は API-21・22・23、状態の変更は API-20、
// 企業研究は API-09、トピックス再取得は API-10。
export async function openCompanyDetail(companyId) {
    const modal = document.getElementById('modal-company-detail');
    modal.hidden = false;

    const statusSelect = document.getElementById('m1-status');
    if (statusSelect.options.length === 0) {
        SELECTION_STATUSES.forEach((s) => statusSelect.add(new Option(s, s)));
    }

    const data = await api(`/api/v1/companies/${companyId}`);
    // TODO: 見出し（企業名・業界・状態）と4つのタブの中身を描く。
    //   #m1-tab-research  … AI企業研究（事業の概要・強み・トピックス）と再取得ボタン
    //   #m1-tab-login     … 採用サイトのログイン情報（URL・ID・パスワード）
    //   #m1-tab-es        … この企業の ES の一覧と「ESを編集」
    //   #m1-tab-job       … 求人情報のメモ
    void data;

    // ジョブの状態は API-11 で確かめる（3秒ごと）
    async function pollJob(jobId) {
        for (;;) {
            const job = await api(`/api/v1/jobs/${jobId}`);
            if (job?.status === 'succeeded' || job?.status === 'failed') return job.status;
            await new Promise((r) => setTimeout(r, 3000));
        }
    }

    document.getElementById('m1-btn-research')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
            const data = await api(`/api/v1/companies/${companyId}/research`, { method: 'POST' });
            // 処理中・開始できたときは job_id が返る（API定義書 v18 6.5）。
            // メインAI不可のときは job_id が無く、研究結果タブに失敗表示が出る想定。
            if (data?.job_id) await pollJob(data.job_id);
            // TODO: 終わったら API-19 で読み直す（A.4 の記入後に作る）
        } catch (err) {
            alert(err.message); // 上限（429）の確定文言など
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('m1-btn-topics')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
            const data = await api(`/api/v1/companies/${companyId}/topics/refresh`, { method: 'POST' });
            if (data?.job_id) await pollJob(data.job_id);
            // TODO: 終わったら API-19 で読み直す（A.4 の記入後に作る）
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('m1-btn-reveal')?.addEventListener('click', async () => {
        const info = await api(`/api/v1/companies/${companyId}/login-info/password`, {
            method: 'POST',
        });
        document.getElementById('m1-login-password').value = info?.password ?? '';
    });
    document.getElementById('m1-btn-save-login')?.addEventListener('click', async () => {
        await api(`/api/v1/companies/${companyId}/login-info`, {
            method: 'PUT',
            body: {
                login_url: document.getElementById('m1-login-url').value,
                login_id: document.getElementById('m1-login-id').value,
                // パスワード欄が空のときは、今のパスワードを送る（変えるときだけ入れる）
                password: document.getElementById('m1-login-password').value,
            },
        });
    });
    document.getElementById('m1-btn-delete')?.addEventListener('click', async () => {
        // 企業削除は API-41（決定14）。確認の文言は基本設計書 v16 4.5 No.41 の通り出す。
        if (!confirm('この企業を削除すると、ES・予定・タスクも削除されます。元に戻せません。削除しますか')) {
            return;
        }
        await api(`/api/v1/companies/${companyId}`, { method: 'DELETE' });
        modal.hidden = true;
        location.reload();
    });
    modal.querySelector('[data-close]')?.addEventListener('click', () => {
        modal.hidden = true;
    });
}
