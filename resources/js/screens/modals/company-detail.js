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

    document.getElementById('m1-btn-research')?.addEventListener('click', async () => {
        await api(`/api/v1/companies/${companyId}/research`, { method: 'POST' });
        // TODO: API-11 で状態を見て、終わったら読み直す
    });
    document.getElementById('m1-btn-topics')?.addEventListener('click', async () => {
        await api(`/api/v1/companies/${companyId}/topics/refresh`, { method: 'POST' });
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
                site_url: document.getElementById('m1-login-url').value,
                login_id: document.getElementById('m1-login-id').value,
                // パスワード欄が空のときは、今のパスワードを送る（変えるときだけ入れる）
                password: document.getElementById('m1-login-password').value,
            },
        });
    });
    document.getElementById('m1-btn-delete')?.addEventListener('click', async () => {
        // TODO: 企業削除は API-41。ルートの追加が前提（routes/api.php に未追加）。
        // 確認の文言は基本設計書 4.5 No.41 の通り出すこと。
    });
    modal.querySelector('[data-close]')?.addEventListener('click', () => {
        modal.hidden = true;
    });
}
