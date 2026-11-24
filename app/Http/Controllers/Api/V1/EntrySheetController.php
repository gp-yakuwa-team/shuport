<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEntrySheetRequest;
use App\Jobs\AiJobBase;
use App\Jobs\GenerateEsDraftJob;
use App\Jobs\ResearchCompanyJob;
use App\Models\AiJob;
use App\Models\Company;
use App\Models\EntrySheet;
use App\Models\EsAnswer;
use App\Models\User;
use App\Support\Ai\GeminiApiService;
use App\Support\AiUsageGate;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntrySheetController extends Controller
{
    /**
     * API-08 ES新規作成＆一括AI生成開始（API定義書 v18 6.4）。
     * ES と設問を作成し、GenerateEsDraftJob を投入して 202 を返す。
     * 対象企業に企業研究の結果がない場合は、企業研究も開始する（企業研究の回数制限の範囲内）。
     * AI生成の上限に達している場合は、ジョブを開始せず 429 を返す。
     */
    public function store(StoreEntrySheetRequest $request): JsonResponse
    {
        $user = $request->user();

        $company = Company::query()
            ->where('user_id', $user->id)
            ->find($request->validated('company_id'));

        if ($company === null) {
            return ProblemDetails::response(404, '企業が見つかりません', $request);
        }

        if ($limitResponse = $this->generationLimitResponse($request, $user)) {
            return $limitResponse;
        }

        $entrySheet = DB::transaction(function () use ($request, $company) {
            $entrySheet = EntrySheet::create([
                'company_id' => $company->id,
                'title' => $request->validated('title'),
            ]);

            foreach ($request->validated('questions') as $question) {
                EsAnswer::create([
                    'entry_sheet_id' => $entrySheet->id,
                    'item_type' => EsAnswer::ITEM_TYPES[$question['type']],
                    'question' => $question['question_text'],
                    'max_chars' => $question['max_chars'],
                    'sort_order' => $question['order'],
                ]);
            }

            return $entrySheet;
        });

        $researchJobId = $this->startResearchIfNeeded($user, $company);
        $generationJob = $this->startGeneration($user, $company, $entrySheet);

        return response()->json(['data' => [
            'entry_sheet_id' => $entrySheet->id,
            'company_id' => $company->id,
            'generation_job_id' => $generationJob->id,
            'research_job_id' => $researchJobId,
            'status' => 'processing',
            'estimated_seconds' => 15,
        ]], 202);
    }

    /**
     * API-13 既存ESの一括AI生成開始（API定義書 v18 6.9）。
     * 同じ ES の生成ジョブが「未処理」「生成中」の間に呼ばれた場合は 409 を返す
     * （5分を経過して「失敗」として扱うジョブは含めない）。
     * 他人の ES、または存在しない場合は 404 を返す。
     */
    public function generate(Request $request, string $entrySheetId): JsonResponse
    {
        $request->validate([
            'tone' => ['nullable', 'string', 'in:formal,confident,passionate'],
        ]);

        $user = $request->user();

        $entrySheet = EntrySheet::query()
            ->whereHas('company', fn ($query) => $query->where('user_id', $user->id))
            ->find($entrySheetId);

        if ($entrySheet === null) {
            return ProblemDetails::response(404, 'ESが見つかりません', $request);
        }

        $inFlight = AiJob::query()
            ->where('entry_sheet_id', $entrySheet->id)
            ->where('job_type', 'ES生成')
            ->whereIn('status', ['未処理', '生成中'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($inFlight) {
            return ProblemDetails::response(409, '同じESの生成ジョブが処理中です', $request);
        }

        if ($limitResponse = $this->generationLimitResponse($request, $user)) {
            return $limitResponse;
        }

        $company = $entrySheet->company;
        $researchJobId = $this->startResearchIfNeeded($user, $company);
        $generationJob = $this->startGeneration($user, $company, $entrySheet);

        return response()->json(['data' => [
            'entry_sheet_id' => $entrySheet->id,
            'company_id' => $company->id,
            'generation_job_id' => $generationJob->id,
            'research_job_id' => $researchJobId,
            'status' => 'processing',
            'estimated_seconds' => 15,
        ]], 202);
    }

    /**
     * API-24 ES取得: ES作成・編集画面（No.7）の内容を返す（企業名・業界、ES名、設問、回答、AIの初案）。
     * 他人の ES・存在しない ES は 404。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function show(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-25 ES保存: ES名、設問の追加・削除・並べ替え、回答を保存する
     * （AIの初案の採用・破棄の結果を含む）。設問は最大30件、最大文字数は2,000まで（サーバー側で確認）。
     * ※ 付録A A.4 の記入後に作る（決定24・AGENTS.md）。受け口のみ。
     */
    public function update(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-26 ES PDF出力: ES の PDF を返す。生成ファイルはサーバーに保存せず、
     * 画面がアクセストークン付きで呼び、受け取ったファイルをブラウザで保存する（基本設計書 v16 4.6）。
     * 出力と最大文字数の判定は回答欄だけが対象（要件定義書 v18 5.3）。
     * ※ PDF ライブラリの追加指示後に作る。受け口のみ。
     */
    public function pdf(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * AI生成の上限（一般は直前1時間に20回、体験用は全体で1日100回）に
     * 達していたら 429 の応答を返す（API定義書 v18 第5章）。
     */
    private function generationLimitResponse(Request $request, User $user): ?JsonResponse
    {
        if ($user->is_demo) {
            return AiUsageGate::demoAllowed()
                ? null
                : ProblemDetails::response(429, '本日の体験用AI生成の上限に達しました', $request);
        }

        return AiUsageGate::generationAllowed($user->id)
            ? null
            : ProblemDetails::response(429, '1時間あたりのAI生成の上限に達しました。時間をおいて再度お試しください', $request);
    }

    /**
     * 対象企業に企業研究の結果がなければ、企業研究を開始して job_id を返す。
     * 上限・メインAI不可のときは開始せず null（ES生成は研究なしで完走させる）。
     */
    private function startResearchIfNeeded(User $user, Company $company): ?string
    {
        if ($company->research_generated_at !== null) {
            return null;
        }

        if (! AiUsageGate::researchAllowed($user->id) || ! app(GeminiApiService::class)->isAvailable()) {
            return null;
        }

        AiUsageGate::record($user->id, (string) config('ai.gemini.model'), '企業研究');
        $job = AiJobBase::createRecord('企業研究', $company->id);
        ResearchCompanyJob::dispatch($job->id);

        return $job->id;
    }

    /**
     * AI生成の回数を記録し、ai_jobs 行を作って GenerateEsDraftJob を投入する。
     */
    private function startGeneration(User $user, Company $company, EntrySheet $entrySheet): AiJob
    {
        AiUsageGate::record($user->id, (string) config('ai.gemini.model'), 'ES生成');
        $job = AiJobBase::createRecord('ES生成', $company->id, $entrySheet->id);
        GenerateEsDraftJob::dispatch($job->id);

        return $job;
    }
}
