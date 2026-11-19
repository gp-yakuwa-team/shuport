<?php

use App\Http\Middleware\AuthenticateSupabaseToken;
use App\Http\Middleware\EnsureNotDemoUser;
use App\Http\Middleware\VerifyLineSignature;
use App\Http\Middleware\VerifySupabaseSession;
use App\Support\ErrorRecorder;
use App\Support\ProblemDetails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Supabase の JWT を検証する認証ガード（B4）
            'supabase.auth' => AuthenticateSupabaseToken::class,
            // セッションの追加確認（API-22・23・38）
            'supabase.session' => VerifySupabaseSession::class,
            // 体験用アカウントでは使えない操作の制限（API-22・36・38）
            'not.demo' => EnsureNotDemoUser::class,
            // LINE Webhook の署名検証（API-12）
            'line.signature' => VerifyLineSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // API のエラー応答は RFC 9457（application/problem+json）に統一する
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ProblemDetails::fromThrowable($e, $request);
            }
        });

        // 異常は error_logs に記録する
        $exceptions->report(function (Throwable $e): void {
            ErrorRecorder::record($e);
        });
    })->create();
