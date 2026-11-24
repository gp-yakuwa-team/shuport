<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompanyRequest;
use App\Jobs\AiJobBase;
use App\Jobs\RefreshCompanyTopicsJob;
use App\Jobs\ResearchCompanyJob;
use App\Models\AiJob;
use App\Models\Company;
use App\Support\Ai\GeminiApiService;
use App\Support\AiUsageGate;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * API-07 企業登録＆AI研究開始判断（API定義書 v18 6.3）。
     * 企業を登録し、原則として ResearchCompanyJob を投入して 202 を返す。
     * 企業研究の上限（体験用は全体の上限）に達している場合とメインAIが使えない場合は、
     * 企業登録のみ完了して 201 を返す（研究を始めなかった場合は回数に数えない）。
     * 同じ企業の二重登録は 409（登録済み企業の company_id を含める）。
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $user = $request->user();
        $name = Company::normalizeName((string) $request->validated('name'));

        $existing = Company::query()
            ->where('user_id', $user->id)
            ->where('normalized_company_name', $name)
            ->first();

        if ($existing !== null) {
            return new JsonResponse([
                'type' => 'about:blank',
                'title' => 'Conflict',
                'status' => 409,
                'detail' => 'この企業はすでに登録されています',
                'instance' => '/'.$request->path(),
                'company_id' => $existing->id,
            ], 409, ['Content-Type' => 'application/problem+json']);
        }

        $company = Company::create([
            'user_id' => $user->id,
            'company_name' => $name,
            'normalized_company_name' => $name,
            'industry' => $request->validated('industry'),
            'job_info_text' => $request->validated('job_description'),
            'selection_status' => '検討中',
        ]);

        $base = [
            'company_id' => $company->id,
            'name' => $company->company_name,
        ];

        if ($user->is_demo && ! AiUsageGate::demoAllowed()) {
            return response()->json(['data' => $base + [
                'job_id' => null,
                'research_job_status' => 'limit_reached',
                'message' => '本日の体験用AI生成の上限に達しました',
            ]], 201);
        }

        if (! AiUsageGate::researchAllowed($user->id)) {
            return response()->json(['data' => $base + [
                'job_id' => null,
                'research_job_status' => 'limit_reached',
                'message' => '企業を登録しました。本日の企業研究の上限（20回）に達したため、企業研究は行っていません',
            ]], 201);
        }

        if (! app(GeminiApiService::class)->isAvailable()) {
            $this->recordFailedJob('企業研究', $company->id);

            return response()->json(['data' => $base + [
                'job_id' => null,
                'research_job_status' => 'skipped_ai_unavailable',
                'message' => '企業研究を作成できませんでした（メインAIの復旧後に再実行できます）',
            ]], 201);
        }

        $job = $this->startResearch($user->id, $company->id);

        return response()->json(['data' => $base + [
            'job_id' => $job->id,
            'research_job_status' => 'queued',
            'estimated_seconds' => 30,
            'message' => '企業登録を完了し、AI企業研究ジョブをキューへ投入しました。',
        ]], 202);
    }

    /**
     * API-09 企業研究の開始・再実行（API定義書 v18 6.5）。
     * 他人の企業は 404。処理中の同じジョブがあれば 202 でその job_id を返す（決定25）。
     * 上限到達時は 429、メインAIが使えない場合は 200 で skipped_ai_unavailable を返す。
     */
    public function research(Request $request, string $companyId): JsonResponse
    {
        $company = $this->ownCompany($request, $companyId);

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $inFlight = $this->inFlightJob($company->id, '企業研究');

        if ($inFlight !== null) {
            return response()->json(['data' => [
                'company_id' => $company->id,
                'job_id' => $inFlight->id,
                'research_job_status' => 'queued',
                'estimated_seconds' => 30,
            ]], 202);
        }

        if (! AiUsageGate::researchAllowed($request->user()->id)) {
            return ProblemDetails::response(429, '本日の企業研究の上限（20回）に達しました。明日以降に再実行できます', $request);
        }

        if (! app(GeminiApiService::class)->isAvailable()) {
            $this->recordFailedJob('企業研究', $company->id);

            return response()->json(['data' => [
                'company_id' => $company->id,
                'job_id' => null,
                'research_job_status' => 'skipped_ai_unavailable',
            ]]);
        }

        $job = $this->startResearch($request->user()->id, $company->id);

        return response()->json(['data' => [
            'company_id' => $company->id,
            'job_id' => $job->id,
            'research_job_status' => 'queued',
            'estimated_seconds' => 30,
        ]], 202);
    }

    /**
     * API-10 最新トピックス再取得（API定義書 v18 6.6）。
     * 他人の企業は 404。処理中の同じジョブがあれば 202 でその job_id を返す（決定25）。
     * 上限到達時は 429、メインAIが使えない場合は 200 で topics_status = unavailable を返す。
     */
    public function refreshTopics(Request $request, string $companyId): JsonResponse
    {
        $company = $this->ownCompany($request, $companyId);

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $inFlight = $this->inFlightJob($company->id, '最新トピックス再取得');

        if ($inFlight !== null) {
            return response()->json(['data' => [
                'company_id' => $company->id,
                'job_id' => $inFlight->id,
                'topics_status' => 'queued',
                'estimated_seconds' => 30,
            ]], 202);
        }

        if (! AiUsageGate::researchAllowed($request->user()->id)) {
            return ProblemDetails::response(429, '本日の企業研究の上限（20回）に達しました。明日以降に再実行できます', $request);
        }

        if (! app(GeminiApiService::class)->isAvailable()) {
            return response()->json(['data' => [
                'company_id' => $company->id,
                'job_id' => null,
                'topics_status' => 'unavailable',
            ]]);
        }

        AiUsageGate::record($request->user()->id, (string) config('ai.gemini.model'), '最新トピックス再取得');
        $job = AiJobBase::createRecord('最新トピックス再取得', $company->id);
        RefreshCompanyTopicsJob::dispatch($job->id);

        return response()->json(['data' => [
            'company_id' => $company->id,
            'job_id' => $job->id,
            'topics_status' => 'queued',
            'estimated_seconds' => 30,
        ]], 202);
    }

    /**
     * API-17 業界の候補: 企業名から業界の候補を1つ返す（No.6）。AI を同期で呼び、
     * AI生成の回数制限（1時間20回）の対象。総 deadline は15秒（仮。AI設計書 V5 3.2.4）。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function industrySuggestion(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-18 企業一覧取得: 企業一覧（No.5）を返す（検索・絞り込み）。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function index(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-19 企業詳細取得: 企業詳細モーダル（M1）の①③④タブの内容と、選考ステータスの履歴を返す。
     * 他人の企業・存在しない企業は 404。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function show(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-20 選考ステータス変更: ステータスを変え、変更の履歴（selection_status_histories）を残す。
     * ステータスは確定9種のみ。他人の企業・存在しない企業は 404。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function updateStatus(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-21 採用サイトログイン情報取得: ②タブの URL と ID を返す（パスワードは返さない。3.4 3-1）。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function showLoginInfo(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-22 採用サイトログイン情報保存: 登録・変更する。セッションの追加確認の対象
     * （トークンの session_id が auth.sessions に存在すること。3.4 1-10）。体験用アカウントは 403。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function updateLoginInfo(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-23 採用サイトのパスワード表示: 伏せ字を外すときにパスワードを返す。
     * セッションの追加確認の対象（3.4 1-10）。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function revealPassword(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
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

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $company->delete();

        return response()->json(null, 204);
    }

    /**
     * 自分の企業を返す。他人の企業・存在しない企業は 404 の problem+json。
     */
    private function ownCompany(Request $request, string $companyId): Company|JsonResponse
    {
        $company = Company::query()
            ->where('user_id', $request->user()->id)
            ->find($companyId);

        if ($company === null) {
            return ProblemDetails::response(404, '企業が見つかりません', $request);
        }

        return $company;
    }

    /**
     * 処理中（未処理・生成中で5分以内）の同じジョブがあれば返す（決定25）。
     */
    private function inFlightJob(string $companyId, string $jobType): ?AiJob
    {
        return AiJob::query()
            ->where('company_id', $companyId)
            ->where('job_type', $jobType)
            ->whereIn('status', ['未処理', '生成中'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest('created_at')
            ->first();
    }

    /**
     * メインAIが使えないときに作る、状態「失敗」の ai_jobs 行（API定義書 v18 6.3 の注記）。
     */
    private function recordFailedJob(string $jobType, string $companyId): void
    {
        $job = AiJobBase::createRecord($jobType, $companyId);
        $job->update(['status' => '失敗', 'finished_at' => now()]);
    }

    /**
     * 企業研究の回数を記録し、ai_jobs 行を作って ResearchCompanyJob を投入する。
     */
    private function startResearch(string $userId, string $companyId): AiJob
    {
        AiUsageGate::record($userId, (string) config('ai.gemini.model'), '企業研究');
        $job = AiJobBase::createRecord('企業研究', $companyId);
        ResearchCompanyJob::dispatch($job->id);

        return $job;
    }
}
