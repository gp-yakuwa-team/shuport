import { api } from '../lib/api.js';
import { INDUSTRIES } from '../lib/constants.js';

// No.6 企業追加（詳細設計書 第4章）。
// 業界の候補は API-17（同期）、登録は API-07。登録すると企業研究（BG-01）が始まる。
export default function init() {
    const form = document.getElementById('company-form');
    const industry = document.getElementById('industry');
    INDUSTRIES.forEach((name) => industry.add(new Option(name, name)));

    // 企業名を入れ終わったら、業界の候補を聞く
    document.getElementById('company-name')?.addEventListener('blur', async (e) => {
        const name = e.target.value.trim();
        if (!name) return;
        try {
            const data = await api('/api/v1/companies/industry-suggestion', {
                method: 'POST',
                body: { company_name: name },
            });
            if (data?.industry && INDUSTRIES.includes(data.industry)) {
                industry.value = data.industry;
            }
        } catch {
            // 候補が取れなくても、手で選べるので続ける
        }
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const action = e.submitter?.value; // register / register-and-es
        const data = await api('/api/v1/companies', {
            method: 'POST',
            body: {
                company_name: form.company_name.value,
                industry: industry.value,
                job_info_text: form.job_info_text.value,
            },
        });
        // TODO: 企業研究の上限で研究を始めなかったときは #research-limit-notice を出す
        // （判定に使う項目は付録A A.4 の API-07 で確定する。登録自体はそのまま進む）
        if (action === 'register-and-es' && data?.entry_sheet_id) {
            location.href = `/entry-sheets/${data.entry_sheet_id}`;
        } else {
            location.href = '/companies';
        }
    });
}
