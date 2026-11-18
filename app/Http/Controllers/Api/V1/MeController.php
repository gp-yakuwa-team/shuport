<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * API-35 ログイン中の利用者の情報: 体験用アカウントか、LINE 連携の状態、LINE 通知の停止状態を返す（設定画面 No.10 の表示と各画面のボタンの出し分けに使う）。
     */
    public function show(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
