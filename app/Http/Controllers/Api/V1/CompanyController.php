<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompanyRequest;
use App\Jobs\AiJobBase;
use App\Jobs\RefreshCompanyTopicsJob;
use App\Jobs\ResearchCompanyJob;
use App\Models\AiJob;
use App\Models\Company;
use App\Models\CompanyLoginInfo;
use App\Support\Ai\AiGateway;
use App\Support\Ai\AiServiceException;
use App\Support\Ai\GeminiApiService;
use App\Support\AiUsageGate;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function __construct(
        private readonly GeminiApiService $gemini,
        private readonly AiGateway $gateway,
    ) {}

    /**
     * API-07 企業登録＆AI研究開始判断。
     * 企業を登録し、原則として ResearchCompanyJob を投入して 202 を返す。
     * 企業研究の上限に達している場合とメインAIが使えない場合は、
     * 企業登録のみ完了して 201 を返す。
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $normalized = Company::normalizeName($validated['company_name']);

        $exists = Company::query()
            ->where('user_id', $user->id)
            ->where('normalized_company_name', $normalized)
            ->exists();
        if ($exists) {
            return ProblemDetails::response(409, 'この企業はすでに登録されています', $request);
        }

        $company = Company::create([
            'user_id' => $user->id,
            'company_name' => $normalized,
            'normalized_company_name' => $normalized,
            'industry' => $validated['industry'],
            'job_info_text' => $validated['job_info_text'] ?? null,
            'selection_status' => '検討中',
        ]);

        if (! AiUsageGate::researchAllowed($user->id)) {
            return response()->json([
                'company_id' => $company->id,
                'research_job_status' => 'limit_reached',
            ], 201);
        }

        if (! $this->gemini->isAvailable()) {
            return response()->json([
                'company_id' => $company->id,
                'research_job_status' => 'skipped_ai_unavailable',
            ], 201);
        }

        $record = AiJobBase::createRecord('企業研究', $company->id);
        AiUsageGate::record($user->id, (string) config('ai.gemini.model'), '企業研究');
        ResearchCompanyJob::dispatch($company->id, $record->id);

        return response()->json([
            'company_id' => $company->id,
            'research_job_status' => 'researching',
            'job_id' => $record->id,
        ], 202);
    }

    /**
     * API-09 企業研究の開始・再実行。
     * 他人の企業は 404 を返す。企業研究の上限に達した場合は 429 を返す。
     * 処理中のジョブがある場合は、新規投入せず 202 で同じ job_id を返す（決定25）。
     * 30日以内の研究結果がある場合は再利用し、投入しない（回数も数えない）。
     */
    public function research(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();
        $company = $this->ownCompany($request, $companyId);

        $processing = $this->processingJob($company->id, '企業研究');
        if ($processing !== null) {
            return response()->json(['job_id' => $processing->id], 202);
        }

        if ($company->research_generated_at !== null
            && $company->research_generated_at->greaterThan(now()->subDays(30))) {
            return response()->json([
                'company_id' => $company->id,
                'research_job_status' => 'reused',
            ]);
        }

        if (! AiUsageGate::researchAllowed($user->id)) {
            return ProblemDetails::response(429, '本日の企業研究の上限（20回）に達しています', $request);
        }

        if (! $this->gemini->isAvailable()) {
            return ProblemDetails::response(503, 'AIが利用できないため、企業研究を開始できません', $request);
        }

        $record = AiJobBase::createRecord('企業研究', $company->id);
        AiUsageGate::record($user->id, (string) config('ai.gemini.model'), '企業研究');
        ResearchCompanyJob::dispatch($company->id, $record->id);

        return response()->json(['job_id' => $record->id], 202);
    }

    /**
     * API-10 最新トピックス再取得。
     * 他人の企業は 404 を返す。企業研究の上限に達した場合は 429 を返す。
     * 処理中のジョブがある場合は、新規投入せず 202 で同じ job_id を返す（決定25）。
     */
    public function refreshTopics(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();
        $company = $this->ownCompany($request, $companyId);

        $processing = $this->processingJob($company->id, '最新トピックス再取得');
        if ($processing !== null) {
            return response()->json(['job_id' => $processing->id], 202);
        }

        if (! AiUsageGate::researchAllowed($user->id)) {
            return ProblemDetails::response(429, '本日の企業研究の上限（20回）に達しています', $request);
        }

        if (! $this->gemini->isAvailable()) {
            return ProblemDetails::response(503, 'AIが利用できないため、最新トピックスの再取得を開始できません', $request);
        }

        $record = AiJobBase::createRecord('最新トピックス再取得', $company->id);
        AiUsageGate::record($user->id, (string) config('ai.gemini.model'), '最新トピックス再取得');
        RefreshCompanyTopicsJob::dispatch($company->id, $record->id);

        return response()->json(['job_id' => $record->id], 202);
    }

    /**
     * API-17 業界の候補: 企業名から業界の候補を1つ返す（No.6）。AI を同期で呼び、
     * AI生成の回数制限（1時間20回）の対象。総 deadline は15秒（仮。AI設計書 V5 3.2.4）。
     * 候補が取れない場合は industry: null を返し、画面は手入力で続けられる
     * （画面詳細設計書（企業追加・ES作成・帳票）v0.2 3.3）。
     */
    public function industrySuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:100'],
        ]);
        $user = $request->user();

        if (! AiUsageGate::generationAllowed($user->id)) {
            return ProblemDetails::response(429, 'AI生成の上限（1時間に20回）に達しています', $request);
        }

        $prompt = $this->industryPrompt($validated['company_name']);
        if ($prompt === null) {
            // プロンプト本文（4.11 AIプロンプト設計詳細設計書）が未配置の間は候補を返さない
            return response()->json(['industry' => null]);
        }

        try {
            $result = $this->gateway->generateJson('業界の候補', $prompt);
        } catch (AiServiceException) {
            return response()->json(['industry' => null]);
        }

        AiUsageGate::record($user->id, (string) config('ai.gemini.model'), '業界の候補');

        $industry = $result['industry'] ?? null;
        if (! is_string($industry) || ! in_array($industry, Company::INDUSTRIES, true)) {
            $industry = null;
        }

        return response()->json(['industry' => $industry]);
    }

    /**
     * API-18 企業一覧取得: 企業一覧（No.5）を返す（検索・絞り込み）。
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::query()->where('user_id', $request->user()->id);

        $search = $request->query('search');
        if (is_string($search) && $search !== '') {
            $query->where('company_name', 'like', '%'.$search.'%');
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('selection_status', $status);
        }

        return response()->json([
            'companies' => $query->orderByDesc('created_at')->get(),
        ]);
    }

    /**
     * API-19 企業詳細取得: 企業詳細モーダル（M1）の①③④タブの内容と、選考ステータスの履歴を返す。
     * 他人の企業・存在しない企業は 404。
     */
    public function show(Request $request, string $companyId): JsonResponse
    {
        $company = $this->ownCompany($request, $companyId);
        $company->load(['statusHistories' => fn ($query) => $query->orderByDesc('changed_at')]);

        return response()->json([
            'company' => $company,
            'has_login_info' => $company->loginInfo()->exists(),
        ]);
    }

    /**
     * API-20 選考ステータス変更: ステータスを変え、変更の履歴（selection_status_histories）を残す。
     * ステータスは確定9種のみ。他人の企業・存在しない企業は 404。
     */
    public function updateStatus(Request $request, string $companyId): JsonResponse
    {
        $validated = $request->validate([
            'selection_status' => ['required', 'string', Rule::in(Company::SELECTION_STATUSES)],
        ]);
        $company = $this->ownCompany($request, $companyId);

        if ($company->selection_status !== $validated['selection_status']) {
            DB::transaction(function () use ($company, $validated) {
                $from = $company->selection_status;
                $company->update(['selection_status' => $validated['selection_status']]);
                $company->statusHistories()->create([
                    'from_status' => $from,
                    'to_status' => $validated['selection_status'],
                    'changed_at' => now(),
                ]);
            });
        }

        return response()->json([
            'company_id' => $company->id,
            'selection_status' => $company->selection_status,
        ]);
    }

    /**
     * API-21 採用サイトログイン情報取得: ②タブの URL と ID を返す（パスワードは返さない。3.4 3-1）。
     */
    public function showLoginInfo(Request $request, string $companyId): JsonResponse
    {
        $company = $this->ownCompany($request, $companyId);
        $info = $company->loginInfo;

        return response()->json([
            'login_url' => $info?->login_url,
            'login_id' => $info?->login_id_encrypted !== null
                ? Crypt::decryptString($info->login_id_encrypted)
                : null,
        ]);
    }

    /**
     * API-22 採用サイトログイン情報保存: 登録・変更する。セッションの追加確認の対象
     * （トークンの session_id が auth.sessions に存在すること。3.4 1-10）。体験用アカウントは 403。
     * ID とパスワードは APP_KEY で暗号化して保存する。
     */
    public function updateLoginInfo(Request $request, string $companyId): JsonResponse
    {
        $validated = $request->validate([
            'login_url' => ['nullable', 'string', 'max:2048'],
            'login_id' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);
        $company = $this->ownCompany($request, $companyId);

        CompanyLoginInfo::updateOrCreate(
            ['company_id' => $company->id],
            [
                'login_url' => $validated['login_url'] ?? null,
                'login_id_encrypted' => ! empty($validated['login_id'])
                    ? Crypt::encryptString($validated['login_id'])
                    : null,
                'password_encrypted' => ! empty($validated['password'])
                    ? Crypt::encryptString($validated['password'])
                    : null,
            ],
        );

        return response()->json([
            'login_url' => $validated['login_url'] ?? null,
            'login_id' => $validated['login_id'] ?? null,
        ]);
    }

    /**
     * API-23 採用サイトのパスワード表示: 伏せ字を外すときにパスワードを返す。
     * セッションの追加確認の対象（3.4 1-10）。
     */
    public function revealPassword(Request $request, string $companyId): JsonResponse
    {
        $company = $this->ownCompany($request, $companyId);
        $info = $company->loginInfo;

        if ($info === null || $info->password_encrypted === null) {
            return ProblemDetails::response(404, 'パスワードが登録されていません', $request);
        }

        return response()->json([
            'password' => Crypt::decryptString($info->password_encrypted),
        ]);
    }

    /**
     * API-41 企業削除: 企業と、その企業に紐づく ES・予定・タスク・
     * 採用サイトログイン情報・選考ステータス履歴を連鎖して削除する。
     * AI の利用記録（ai_usage_logs）は消さない（回数は戻らない）。
     * 他人の企業は 404 を返す。体験用アカウントは not.demo で 403（決定14）。
     */
    public function destroy(Request $request, string $companyId): JsonResponse
    {
        $company = $this->ownCompany($request, $companyId);
        $company->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * 自分の企業を返す。他人の企業・存在しない企業は 404（problem+json）。
     */
    private function ownCompany(Request $request, string $companyId): Company
    {
        return Company::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $companyId)
            ->firstOrFail();
    }

    /**
     * 処理中（未処理・生成中）のジョブを返す。5分を超えた行は失敗として扱うため対象にしない
     * （AiJobBase の読み取り側の判定）。
     */
    private function processingJob(string $companyId, string $jobType): ?AiJob
    {
        return AiJob::query()
            ->where('company_id', $companyId)
            ->where('job_type', $jobType)
            ->whereIn('status', ['未処理', '生成中'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * 業界の候補のプロンプト（4.11 の本文）を読み、企業名を差し込む。
     * 配置場所は config/ai.php の prompts.industry。未配置の間は null。
     */
    private function industryPrompt(string $companyName): ?string
    {
        $path = config('ai.prompts.industry');
        if (! is_string($path) || $path === '') {
            return null;
        }

        $fullPath = base_path($path);
        if (! is_file($fullPath)) {
            return null;
        }

        $template = file_get_contents($fullPath);
        if ($template === false) {
            return null;
        }

        return str_replace('{company_name}', $companyName, $template);
    }
}
