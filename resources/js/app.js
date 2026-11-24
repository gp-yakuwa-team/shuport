import { requireSession, startInactivityWatch } from './lib/session.js';

// 画面ごとの処理の入口。レイアウトの body[data-page] で振り分ける。
const PROTECTED = [
    'dashboard',
    'mypage',
    'companies',
    'company-create',
    'entry-sheet',
    'calendar',
    'tasks',
    'settings',
];

const pages = {
    login: () => import('./screens/login.js'),
    register: () => import('./screens/register.js'),
    'forgot-password': () => import('./screens/forgot-password.js'),
    'reset-password': () => import('./screens/reset-password.js'),
    dashboard: () => import('./screens/dashboard.js'),
    mypage: () => import('./screens/mypage.js'),
    companies: () => import('./screens/companies.js'),
    'company-create': () => import('./screens/company-create.js'),
    'entry-sheet': () => import('./screens/entry-sheet.js'),
    calendar: () => import('./screens/calendar.js'),
    tasks: () => import('./screens/tasks.js'),
    settings: () => import('./screens/settings.js'),
};

const page = document.body?.dataset.page;
if (page && pages[page]) {
    (async () => {
        if (PROTECTED.includes(page)) {
            // ログイン後の画面は、セッションが無ければログイン画面へ（3.4 1-3）
            const session = await requireSession();
            if (!session) return;
            startInactivityWatch();
        }
        const mod = await pages[page]();
        await mod.default?.();
    })();
}
