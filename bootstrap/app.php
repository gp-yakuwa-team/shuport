<?php

use App\Support\ErrorRecorder;
use App\Support\ProblemDetails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
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
