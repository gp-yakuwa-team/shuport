<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * API-11 ジョブ進捗取得。
     * ai_jobs の状態と進捗を返す。他人のジョブ、または存在しない場合は 404 を返す。
     * 画面は3秒間隔でポーリングする。
     */
    public function show(Request $request, string $jobId): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }
}
