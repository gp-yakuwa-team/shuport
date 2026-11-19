<?php

namespace App\Http\Middleware;

use App\Support\ProblemDetails;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LINE Webhook の署名検証（API定義書 v18 第3章）。
 * チャネルシークレットで HMAC-SHA256 を計算し、X-Line-Signature と照合する。
 * 失敗した場合は 401 を返し、後段の処理は行わない。
 * 生の本文や署名の値はログに出さない。
 */
class VerifyLineSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('line.channel_secret');
        $signature = (string) $request->header('X-Line-Signature', '');

        $expected = $secret !== ''
            ? base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true))
            : '';

        if ($expected === '' || ! hash_equals($expected, $signature)) {
            return ProblemDetails::response(401, '署名の検証に失敗しました', $request);
        }

        return $next($request);
    }
}
