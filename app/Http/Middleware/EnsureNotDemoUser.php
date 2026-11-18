<?php

namespace App\Http\Middleware;

use App\Support\ProblemDetails;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotDemoUser
{
    /**
     * 体験用アカウントで行えない操作の制限（API定義書 v18 6.1、3.4 セキュリティ設計 6-4）:
     * 退会、LINE連携（コード発行）、採用サイトログイン情報の登録・変更（API-38・36・22）。
     * 体験用アカウントの場合は 403 を返す。supabase.auth の後ろに付ける前提。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->is_demo) {
            return ProblemDetails::response(403, '体験用アカウントでは利用できません', $request);
        }

        return $next($request);
    }
}
