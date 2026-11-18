<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoController extends Controller
{
    /**
     * API-39 体験用ログイン: 体験用アカウントでログインし、トークンを画面に返す。認証の対象外。有効期間の外は拒否し、接続元の IP ごとに回数を制限する（制限の文言は詳細設計で決める）。
     */
    public function login(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
