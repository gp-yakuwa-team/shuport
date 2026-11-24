<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * API-32 タスク一覧取得: タスクの一覧を返す（No.9）。
     */
    public function index(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-33 タスク登録: タスクを登録する（M3）。
     */
    public function store(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-34 タスク変更: タスクを変える（M3。完了チェックを含む）。
     */
    public function update(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-42 タスク削除: タスクを削除する（M3・No.9）。
     * API定義書 v18 に受け口が無かったため新設した（予定の削除 API-31 と
     * そろえ、変更 API-34 とは分けている）。他人のタスクは 404 を返す。
     * API定義書と詳細設計書（付録A A.4）への追加が別途必要。
     */
    public function destroy(Request $request, string $taskId): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
