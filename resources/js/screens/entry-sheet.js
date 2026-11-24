import { api, apiFile } from '../lib/api.js';

// No.7 ES作成・編集（詳細設計書 第4章）。
// 取得 API-24、保存 API-25、AI生成 API-13、状態 API-11、PDF API-26、Excel API-27。
export default async function init() {
    const id = location.pathname.split('/').pop();

    async function load() {
        const data = await api(`/api/v1/entry-sheets/${id}`);
        // TODO: #es-company に企業名、#es-title に ES の名前、
        // #question-list に設問（設問文・回答欄・文字数・AI の初案）を描く。
        // 文字数と PDF は回答欄だけを対象にする（要件定義書 v18）。
        void data;
    }

    async function save() {
        // TODO: #question-list の回答欄の値を集めて送る
        await api(`/api/v1/entry-sheets/${id}`, { method: 'PUT', body: {} });
    }

    async function pollJob(jobId) {
        // 状態は API-11 で確かめる（3秒ごと）
        for (;;) {
            const job = await api(`/api/v1/jobs/${jobId}`);
            if (job?.status === 'succeeded' || job?.status === 'failed') return job.status;
            await new Promise((r) => setTimeout(r, 3000));
        }
    }

    document.getElementById('btn-save')?.addEventListener('click', save);
    document.getElementById('btn-generate')?.addEventListener('click', async () => {
        await save(); // 画面は先に保存してから生成を呼ぶ（決定7）
        const progress = document.getElementById('generate-progress');
        progress.hidden = false;
        const data = await api(`/api/v1/entry-sheets/${id}/generate`, { method: 'POST' });
        await pollJob(data.job_id);
        progress.hidden = true;
        await load();
    });
    document.getElementById('btn-pdf')?.addEventListener('click', async () => {
        const blob = await apiFile(`/api/v1/entry-sheets/${id}/pdf`);
        download(blob, 'es.pdf');
    });
    document.getElementById('btn-excel')?.addEventListener('click', async () => {
        const blob = await apiFile('/api/v1/resume/excel');
        download(blob, 'resume.xlsx');
    });

    await load();
}

function download(blob, filename) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
}
