<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompanyRequest;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * API-07 企業登録＆AI研究開始判断。
     * 企業を登録し、原則として ResearchCompanyJob を投入して 202 を返す。
     * 企業研究の上限に達している場合とメインAIが使えない場合は、
     * 企業登録のみ完了して 201 を返す。
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }

    /**
     * API-09 企業研究の開始・再実行。
     * 他人の企業は 404 を返す。企業研究の上限に達した場合は 429 を返す。
     */
    public function research(Request $request, string $companyId): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }

    /**
     * API-10 最新トピックス再取得。
     * 他人の企業は 404 を返す。企業研究の上限に達した場合は 429 を返す。
     */
    public function refreshTopics(Request $request, string $companyId): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }
}
