<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiJob;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /** @var array<string, string> DB の job_type => API の job_type（API定義書 v18 6.7） */
    private const JOB_TYPES = [
        '企業研究' => 'research_company',
        'ES生成' => 'generate_es_draft',
        '最新トピックス再取得' => 'refresh_topics',
    ];

    /**
     * API-11 ジョブ進捗取得（API定義書 v18 6.7）。
     * ai_jobs の状態と進捗を返す。他人のジョブ、または存在しない場合は 404 を返す。
     * 画面は3秒間隔でポーリングする。
     * 「未処理」「生成中」のまま5分を経過したジョブは「失敗」として扱う。
     * 失敗の理由のコードは返さない（error_logs に置く。決定27）。
     */
    public function show(Request $request, string $jobId): JsonResponse
    {
        $job = AiJob::find($jobId);

        if ($job === null || ! $this->isOwnedBy($job, $request->user()->id)) {
            return ProblemDetails::response(404, 'ジョブが見つかりません', $request);
        }

        return response()->json(['data' => [
            'job_id' => $job->id,
            'job_type' => self::JOB_TYPES[$job->job_type] ?? $job->job_type,
            'status' => $job->apiStatus(),
            'progress_step' => $job->progress_step,
            'company_id' => $job->company_id,
            'entry_sheet_id' => $job->entry_sheet_id,
        ]]);
    }

    /**
     * ジョブの所有者は、紐づく企業（または ES の企業）の所有者で判定する
     * （ai_jobs に user_id は無い。DB定義書 v7）。
     */
    private function isOwnedBy(AiJob $job, string $userId): bool
    {
        if ($job->company !== null) {
            return $job->company->user_id === $userId;
        }

        if ($job->entrySheet !== null) {
            return $job->entrySheet->company?->user_id === $userId;
        }

        return false;
    }
}
