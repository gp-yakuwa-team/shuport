<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineLinkController extends Controller
{
    /**
     * API-36 LINE連携コード発行: 連携コードを発行する（10分・1回だけ有効）。体験用アカウントは 403。連携の確定は LINE Webhook（API-12）側で行う。
     */
    public function store(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-37 LINE連携解除: 連携を解除する。成功時は 204 No Content。
     */
    public function destroy(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
