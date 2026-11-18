<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * API-15 マイページ取得: マイページ（No.4）の内容を返す。
     */
    public function show(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-16 マイページ保存: 学歴、必須・任意項目、資格、補足項目を保存する。
     */
    public function update(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
