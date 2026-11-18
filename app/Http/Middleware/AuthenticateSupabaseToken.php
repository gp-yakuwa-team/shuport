<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ProblemDetails;
use App\Support\Supabase\JwtVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateSupabaseToken
{
    public function __construct(private JwtVerifier $verifier) {}

    /**
     * Authorization: Bearer の Supabase JWT を検証し、対応する利用者（public.users）を
     * 認証利用者にする。Cookie では認証しない（2026年11月18日 決定13）。
     * トークンがない・検証できない・利用者がいない場合は 401（problem+json）。
     * v1 グループへの適用は B4（認証ガード）で行う。
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

        $subject = $claims->sub ?? null;
        $user = is_string($subject) ? User::find($subject) : null;
        if ($user === null) {
            return ProblemDetails::response(401, '利用者が見つかりません', $request);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
