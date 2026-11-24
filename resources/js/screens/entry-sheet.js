import { api, apiFile } from '../lib/api.js';

// No.7 ES作成・編集（画面詳細設計書「企業追加・ES作成・帳票」v0.2 第4章）。
// 新規（/entry-sheets/new?company_id=…）は API-08 で作成と生成開始を1回で行う。
// 保存済みの ES は、取得 API-24、保存 API-25、AI生成 API-13、状態 API-11、PDF API-26、Excel API-27。
// API-24・25 は付録A A.4 の記入後に作るため、編集側の読み込み・保存はまだ TODO。

// 最初の設問の初期値（v0.2 4.4）。常に作る5つ。
const ALWAYS_QUESTIONS = [
    { type: 'self_pr', question_text: '自己PRをご記入ください。', max_chars: 400 },
    { type: 'gakuchika', question_text: '学生時代に力を入れたことを教えてください。', max_chars: 400 },
    { type: 'strength_weakness', question_text: 'あなたの長所と短所を教えてください。', max_chars: 400 },
    { type: 'motivation', question_text: '当社を志望する理由を教えてください。', max_chars: 400 },
    { type: 'qualification', question_text: '保有している資格を教えてください。', max_chars: 200 },
];

// マイページに入力があるときだけ足す3つ（v0.2 4.4）。profileKey は API-15 の項目名。
const OPTIONAL_QUESTIONS = [
    { profileKey: 'extracurricular', type: 'extra_activity', question_text: '学業以外で力を入れたことを教えてください。', max_chars: 400 },
    { profileKey: 'club_circle', type: 'club_circle', question_text: 'クラブ・サークル活動について教えてください。', max_chars: 400 },
    { profileKey: 'hobbies_skills', type: 'hobby_skill', question_text: 'あなたの趣味・特技を教えてください。', max_chars: 200 },
];

async function buildInitialQuestions() {
    const questions = [...ALWAYS_QUESTIONS];
    // API-15 が使えない間は必須の5つだけで作る
    try {
        const profile = await api('/api/v1/profile');
        OPTIONAL_QUESTIONS.forEach(({ profileKey, ...question }) => {
            if (profile?.[profileKey]) questions.push(question);
        });
    } catch {
        // 任意分は足さずに進む
    }
    return questions.map((question, i) => ({ order: i + 1, ...question }));
}

async function pollJob(jobId) {
    // 状態は API-11 で確かめる（3秒ごと）
    for (;;) {
        const job = await api(`/api/v1/jobs/${jobId}`);
        if (job?.status === 'succeeded' || job?.status === 'failed') return job.status;
        await new Promise((r) => setTimeout(r, 3000));
    }
}

export default async function init() {
    const id = location.pathname.split('/').pop();
    const progress = document.getElementById('generate-progress');

    if (id === 'new') {
        // 新規作成: 保存・PDF・Excel はまだ使えない
        ['btn-save', 'btn-pdf', 'btn-excel'].forEach((btnId) => {
            const el = document.getElementById(btnId);
            if (el) el.disabled = true;
        });
        const companyId = new URLSearchParams(location.search).get('company_id');
        document.getElementById('btn-generate')?.addEventListener('click', async () => {
            const titleInput = document.getElementById('es-title');
            const title = titleInput.value.trim();
            if (!title) {
                // ES名が空欄なら、先に入力を求める（v0.2 4.5）
                titleInput.focus();
                return;
            }
            progress.hidden = false;
            try {
                const data = await api('/api/v1/entry-sheets', {
                    method: 'POST',
                    body: {
                        company_id: companyId,
                        title,
                        questions: await buildInitialQuestions(),
                    },
                });
                if (data?.generation_job_id) await pollJob(data.generation_job_id);
                location.href = `/entry-sheets/${data.entry_sheet_id}`;
            } catch (err) {
                progress.hidden = true;
                alert(err.message);
            }
        });
        return;
    }

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

    document.getElementById('btn-save')?.addEventListener('click', save);
    document.getElementById('btn-generate')?.addEventListener('click', async () => {
        await save(); // 画面は先に保存してから生成を呼ぶ（決定7）
        progress.hidden = false;
        const data = await api(`/api/v1/entry-sheets/${id}/generate`, { method: 'POST' });
        // 応答の項目名は API定義書 v18 6.9（generation_job_id / research_job_id）
        if (data?.generation_job_id) await pollJob(data.generation_job_id);
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
