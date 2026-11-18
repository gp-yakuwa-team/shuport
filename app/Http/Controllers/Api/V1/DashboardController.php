<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * API-14 ダッシュボード取得: 直近7日間の予定、締切が近い ES とタスク、選考状況別の件数、未完了のタスク、LINE 通知の停止状態を返す。
     */
    public function show(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
