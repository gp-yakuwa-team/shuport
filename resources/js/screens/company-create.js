import { api } from '../lib/api.js';
import { INDUSTRIES } from '../lib/constants.js';

// No.6 企業追加（画面詳細設計書「企業追加・ES作成・帳票」v0.2 第3章）。
// 業界の候補は API-17（同期）、登録は API-07。登録すると企業研究（BG-01）が始まる。
export default function init() {
    const form = document.getElementById('company-form');
    const nameInput = document.getElementById('company-name');
    const industry = document.getElementById('industry');
    const notice = document.getElementById('research-limit-notice');
    INDUSTRIES.forEach((name) => industry.add(new Option(name, name)));

    // 業界の候補（API-17）: 企業名を入れ終わったときに聞く。
    // 業界が空欄か、前回の候補のまま変わっていないときだけ聞き、同じ企業名では聞き直さない。
    // 失敗・時間切れ（15秒）のときは表示を変えない（v0.2 3.3）。
    let suggestedIndustry = '';
    let lastQueriedName = '';
    nameInput?.addEventListener('blur', async () => {
        const name = nameInput.value.trim();
        if (!name || name === lastQueriedName) return;
        if (industry.value && industry.value !== suggestedIndustry) return;
        lastQueriedName = name;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15000);
        try {
            const data = await api('/api/v1/companies/industry-suggestion', {
                method: 'POST',
                body: { company_name: name },
                signal: controller.signal,
            });
            if (data?.industry && INDUSTRIES.includes(data.industry)) {
                industry.value = data.industry;
                suggestedIndustry = data.industry;
            }
        } catch {
            // 候補が取れなくても、手で選べるので続ける
        } finally {
            clearTimeout(timer);
        }
    });

    function showNotice(html) {
        notice.innerHTML = html;
        notice.hidden = false;
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const action = e.submitter?.value; // register / register-and-es
        let data;
        try {
            // 項目名は API定義書 v18 6.3（name / industry / job_description）
            data = await api('/api/v1/companies', {
                method: 'POST',
                body: {
                    name: nameInput.value.trim(),
                    industry: industry.value,
                    job_description: document.getElementById('job-info-text').value,
                },
            });
        } catch (err) {
            if (err.status === 409) {
                // 二重登録（6.3）。登録済みの企業は企業一覧から開ける
                showNotice(`${err.message} <a href="/companies">企業一覧を開く</a>`);
            } else if (err.status === 422 && err.errors) {
                const first = Object.values(err.errors).flat()[0];
                if (first) showNotice(first);
            } else {
                showNotice(err.message || '登録できませんでした');
            }
            return;
        }
        // 研究を始めなかった場合（本日の上限・メインAI不可）は、
        // API が返す文言を出してこの画面に留まる（v0.2 3.4）
        if (
            data?.research_job_status === 'limit_reached' ||
            data?.research_job_status === 'skipped_ai_unavailable'
        ) {
            showNotice(`${data.message ?? ''} <a href="/companies">企業一覧を開く</a>`);
            return;
        }
        if (action === 'register-and-es') {
            location.href = `/entry-sheets/new?company_id=${data.company_id}`;
        } else {
            location.href = '/companies';
        }
    });
}
