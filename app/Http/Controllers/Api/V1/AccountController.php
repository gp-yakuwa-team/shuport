<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * API-38 退会: セッションの追加確認（API-22・23 と同じ）の対象。deleted_accounts を作り、ジョブを消し、Supabase の管理用 API で利用者を物理削除する。成功時は 204 No Content。体験用アカウントは 403。
     */
    public function destroy(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
