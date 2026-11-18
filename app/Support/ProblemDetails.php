<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ProblemDetails
{
    /**
     * 例外を RFC 9457（application/problem+json）の応答に変換する。
     * API定義書の共通仕様: 他人のリソースは存在しないものとして 404 を返すため、
     * 見つからない場合と区別がつかない形にそろえる。
     */
    public static function fromThrowable(Throwable $e, Request $request): JsonResponse
    {
        $status = self::statusFor($e);

        $problem = [
            'type' => 'about:blank',
            'title' => self::titleFor($status),
            'status' => $status,
            'detail' => self::detailFor($e, $status),
            'instance' => '/'.$request->path(),
        ];

        if ($e instanceof ValidationException) {
            $problem['errors'] = $e->errors();
        }

        $headers = ['Content-Type' => 'application/problem+json'];

        if ($e instanceof HttpExceptionInterface) {
            $headers = array_merge($headers, $e->getHeaders());
        }

        return new JsonResponse($problem, $status, $headers);
    }

    /**
     * ステータスと detail を指定して problem+json の応答を作る。
     * レート制限の超過時など、例外を経由しない場合に使う。
     *
     * @param  array<string, string>  $headers
     */
    public static function response(int $status, string $detail, Request $request, array $headers = []): JsonResponse
    {
        $problem = [
            'type' => 'about:blank',
            'title' => self::titleFor($status),
            'status' => $status,
            'detail' => $detail,
            'instance' => '/'.$request->path(),
        ];

        return new JsonResponse($problem, $status, array_merge(['Content-Type' => 'application/problem+json'], $headers));
    }

    private static function statusFor(Throwable $e): int
    {
        return match (true) {
            $e instanceof ValidationException => 422,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
    }

    private static function titleFor(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'Error';
    }

    private static function detailFor(Throwable $e, int $status): string
    {
        if ($status >= 500) {
            // 内部の情報は出さない。デバッグ時のみ例外メッセージを返す
            return config('app.debug')
                ? $e->getMessage()
                : 'サーバーエラーが発生しました。時間をおいて再度お試しください。';
        }

        return $e->getMessage() !== '' ? $e->getMessage() : self::titleFor($status);
    }
}
