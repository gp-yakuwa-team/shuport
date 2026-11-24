import { api } from '../lib/api.js';
import { SELECTION_STATUSES } from '../lib/constants.js';
import { openCompanyDetail } from './modals/company-detail.js';

// No.5 企業一覧（詳細設計書 第4章）。一覧は API-18、状態の変更は API-20。
export default async function init() {
    const tbody = document.getElementById('company-rows');
    const statusFilter = document.getElementById('status-filter');
    const search = document.getElementById('company-search');

    SELECTION_STATUSES.forEach((s) => statusFilter.add(new Option(s, s)));

    async function load() {
        const params = new URLSearchParams();
        if (search.value) params.set('q', search.value);
        if (statusFilter.value) params.set('status', statusFilter.value);
        const data = await api(`/api/v1/companies?${params}`);
        tbody.replaceChildren();
        // TODO: 1行の見た目（企業名・業界・研究の状態・ES の有無）は第4章 No.5 の項目で描く。
        // 行の「詳細」ボタンで openCompanyDetail(company.id) を呼ぶこと。
        void data;
    }

    statusFilter.addEventListener('change', load);
    search.addEventListener('change', load);
    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-detail]');
        if (btn) openCompanyDetail(btn.dataset.detail);
    });
    await load();
}
