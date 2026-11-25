<?php

namespace Tests\Feature\Line;

use App\Jobs\ParseLineMessageJob;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\Line\LineApiService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * BG-04 ParseLineMessageJob（P-05）の検証。
 * 本文の取り出し・マスキング・JSON の検証・承認待ちの保存・LINE への返信を確かめる。
 */
class ParseLineMessageJobTest extends TestCase
{
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-25 12:00:00+09:00');
        CarbonImmutable::setTestNow('2026-11-25 12:00:00+09:00');

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'line.channel_access_token' => 'test-line-token',
            'line.reply_token_budget_seconds' => 55,
            'ai.gemini.api_key' => 'test-gemini-key',
            'ai.gemini.model' => 'gemini-3.5-flash-lite',
            'ai.gemini.paid_project_verified' => true,
            'ai.workers_ai.account_id' => 'test-account',
            'ai.workers_ai.api_token' => 'test-cf-token',
            'ai.workers_ai.model' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('line_user_id')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('model_name');
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->timestamp('used_at');
            $table->string('purpose');
        });
        Schema::create('pending_line_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('company_name')->nullable();
            $table->text('event_type');
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->text('meeting_url')->nullable();
            $table->text('memo')->nullable();
            $table->timestamp('expires_at');
        });
        Schema::create('error_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('occurred_at');
            $table->string('error_type');
            $table->string('location')->nullable();
            $table->text('message');
            $table->text('stack_trace')->nullable();
        });

        $this->userId = (string) Str::uuid();

        User::forceCreate([
            'id' => $this->userId,
            'line_user_id' => 'U-linked',
            'terms_accepted_at' => now(),
            'terms_version' => 'v1',
            'is_demo' => false,
        ]);

        DB::table('ai_usage_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'model_name' => 'gemini-3.5-flash-lite',
            'used_at' => now(),
            'purpose' => 'LINEのトーク解析',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function runJob(string $text, ?string $receivedAt = null): string
    {
        $key = (string) Str::uuid();

        Cache::put(
            'line:msg:'.$key,
            Crypt::encryptString(json_encode(['text' => $text, 'reply_token' => 'reply-1'], JSON_THROW_ON_ERROR)),
            120,
        );

        (new ParseLineMessageJob($this->userId, $key, $receivedAt ?? now()->toIso8601String()))
            ->handle(app(LineApiService::class), app(AiGateway::class));

        return $key;
    }

    private function fakeGemini(string $json): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $json]]]],
                ],
                'usageMetadata' => ['promptTokenCount' => 300, 'candidatesTokenCount' => 60],
            ]),
            'api.line.me/*' => Http::response([], 200),
        ]);
    }

    public function test_valid_reply_creates_pending_and_asks_for_confirmation(): void
    {
        $this->fakeGemini(json_encode([
            'event_found' => true,
            'event_type' => '面接',
            'company_name' => '株式会社テスト',
            'date_known' => true,
            'time_known' => true,
            'start_at' => '2026-11-28T14:00:00+09:00',
            'end_at' => '2026-11-28T15:00:00+09:00',
            'meeting_url_ref' => 'URL1',
            'memo' => '持ち物：履歴書',
        ], JSON_THROW_ON_ERROR));

        $this->runJob('11月28日14時から株式会社テストの面接です。https://example.com/meet に参加。連絡先 test@example.com');

        $this->assertDatabaseHas('pending_line_events', [
            'user_id' => $this->userId,
            'company_name' => '株式会社テスト',
            'event_type' => '面接',
            'meeting_url' => 'https://example.com/meet',
            'memo' => '持ち物：履歴書',
        ]);
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $this->userId,
            'purpose' => 'LINEのトーク解析',
            'model_name' => 'gemini-3.5-flash-lite',
            'input_tokens' => 300,
            'output_tokens' => 60,
        ]);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/message/reply')) {
                return false;
            }

            $messages = $request['messages'];

            return str_contains($messages[0]['text'], '予定を登録しますか？')
                && str_contains($messages[0]['text'], '2026年11月28日 14:00〜15:00')
                && count($messages[0]['quickReply']['items']) === 2;
        });

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'generativelanguage')) {
                return false;
            }

            $body = $request->body();

            return str_contains($body, '[EMAIL]')
                && str_contains($body, '[URL1]')
                && ! str_contains($body, 'test@example.com');
        });
    }

    public function test_date_known_without_time_is_rejected_except_es_deadline(): void
    {
        $this->fakeGemini(json_encode([
            'event_found' => true,
            'event_type' => '面接',
            'company_name' => null,
            'date_known' => true,
            'time_known' => false,
            'start_at' => '2026-11-28T00:00:00+09:00',
            'end_at' => null,
            'meeting_url_ref' => null,
            'memo' => null,
        ], JSON_THROW_ON_ERROR));

        $this->runJob('11月28日に面接です');

        $this->assertDatabaseCount('pending_line_events', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/message/reply')
            && $request['messages'][0]['text'] === '日程を取得できませんでした。内容を確認してください');
    }

    public function test_invalid_date_is_rejected(): void
    {
        $this->fakeGemini(json_encode([
            'event_found' => true,
            'event_type' => '面接',
            'company_name' => null,
            'date_known' => true,
            'time_known' => true,
            'start_at' => '2026-02-30T14:00:00+09:00',
            'end_at' => null,
            'meeting_url_ref' => null,
            'memo' => null,
        ], JSON_THROW_ON_ERROR));

        $this->runJob('2月30日14時から面接です');

        $this->assertDatabaseCount('pending_line_events', 0);
    }

    public function test_missing_cache_is_recorded_and_not_sent(): void
    {
        Http::fake();

        (new ParseLineMessageJob($this->userId, 'missing', now()->toIso8601String()))
            ->handle(app(LineApiService::class), app(AiGateway::class));

        Http::assertNothingSent();
        $this->assertDatabaseHas('error_logs', ['error_type' => 'バックグラウンド処理']);
    }

    public function test_expired_reply_token_is_not_used(): void
    {
        Http::fake();

        $this->runJob('11月28日14時から面接です', now()->subSeconds(56)->toIso8601String());

        Http::assertNothingSent();
        $this->assertDatabaseHas('error_logs', ['message' => 'line_reply_token_expired']);
    }

    public function test_ai_failure_replies_parse_failed(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.cloudflare.com/*' => Http::response([], 500),
            'api.line.me/*' => Http::response([], 200),
        ]);

        $this->runJob('11月28日14時から面接です');

        $this->assertDatabaseCount('pending_line_events', 0);
        $this->assertDatabaseHas('error_logs', ['error_type' => '外部サービス']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/message/reply')
            && $request['messages'][0]['text'] === '日程を取得できませんでした。内容を確認してください');
    }
}
