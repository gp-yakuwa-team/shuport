<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * API-40 死活確認: 認証の対象外。DB にも接続し、状態だけを返す（定期アクセス先。要件定義書 v18 4.1）。
     */
    public function show(Request $request): JsonResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            return ProblemDetails::response(503, 'データベースに接続できません', $request);
        }

        return response()->json(['status' => 'ok']);
    }
}
