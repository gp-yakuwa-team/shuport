<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResumeController extends Controller
{
    /**
     * API-27 履歴書Excel出力: 履歴書の Excel を返す。生成ファイルはサーバーに保存せず、
     * 画面がアクセストークン付きで呼び、受け取ったファイルをブラウザで保存する（基本設計書 v16 4.6）。
     */
    public function excel(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
