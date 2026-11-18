<?php

namespace App\Providers;

use App\Support\ProblemDetails;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * レート制限の土台（API定義書 第5章）。
     * 回数の保存先は cache テーブル（CACHE_STORE=database）。
     * AI生成・企業研究・体験用の上限は回数の数え方が異なるため、
     * ミドルウェアではなく AiUsageGate（ai_usage_logs の集計）で判定する。
     */
    private function configureRateLimiting(): void
    {
        // 会員登録要求: 同一 IP から5分あたり30回まで
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinutes(5, 30)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => ProblemDetails::response(429, '時間をおいて再度お試しください', $request, $headers));
        });

        // ログイン要求: 同一 IP から5分あたり150回まで
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(5, 150)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => ProblemDetails::response(429, '時間をおいて再度お試しください', $request, $headers));
        });

        // パスワード再設定: 同一 IP から5分あたり30回まで
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinutes(5, 30)
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => ProblemDetails::response(429, '時間をおいて再度お試しください', $request, $headers));
        });

        // パスワード再設定: 同一ユーザーへは60秒に1回まで
        RateLimiter::for('password-reset-user', function (Request $request) {
            return Limit::perMinute(1)
                ->by($request->input('email', $request->ip()))
                ->response(fn (Request $request, array $headers) => ProblemDetails::response(429, '時間をおいて再度お試しください', $request, $headers));
        });

        // 認証メール: 1時間あたり30通まで
        RateLimiter::for('email-verification', function (Request $request) {
            return Limit::perHour(30)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn (Request $request, array $headers) => ProblemDetails::response(429, '時間をおいて再度お試しください', $request, $headers));
        });

        // LINE連携コードの誤入力: 10分あたり5回まで（保存先は DB のレート制限）
        RateLimiter::for('line-code', function (Request $request) {
            return Limit::perMinutes(10, 5)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn (Request $request, array $headers) => ProblemDetails::response(429, '時間をおいて再度お試しください', $request, $headers));
        });
    }
}
