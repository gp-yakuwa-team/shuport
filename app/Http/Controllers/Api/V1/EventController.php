<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * API-28 予定一覧取得: 期間を指定して予定の一覧を返す（カレンダー No.8）。
     */
    public function index(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-29 予定登録: 予定を登録する（M2）。時間帯が1分でも重なれば警告し、警告後も保存できる（同刻は重複ではない）。
     */
    public function store(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-30 予定変更: 予定を変える（M2）。
     */
    public function update(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-31 予定削除: 予定を消す。成功時は 204 No Content。
     */
    public function destroy(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
