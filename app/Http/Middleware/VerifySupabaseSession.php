<?php

namespace App\Http\Middleware;

use App\Support\ProblemDetails;
use App\Support\Supabase\JwtVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifySupabaseSession
{
    public function __construct(private JwtVerifier $verifier) {}

    /**
     * セッションの追加確認（API定義書 v18 6.1）:
     * 採用サイト認証情報の登録・変更・パスワード表示と退会（API-22・23・38）では、
     * JWT の検証に加えて、トークン内の session_id が auth.sessions に
     * 存在することを確かめる。存在しない場合は 401。
     * supabase.auth の後ろに付ける前提。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return ProblemDetails::response(401, '認証が必要です', $request);
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (Throwable) {
            return ProblemDetails::response(401, 'トークンが無効です', $request);
        }

        if (! $this->verifier->sessionExists($claims)) {
            return ProblemDetails::response(401, 'セッションが無効です', $request);
        }

        return $next($request);
    }
}
