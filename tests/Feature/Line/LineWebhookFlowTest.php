<?php

namespace Tests\Feature\Line;

use App\Jobs\ParseLineMessageJob;
use App\Models\LineOneTimeCode;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * LINE Webhook の同期処理（連携コード・確認・解析の受け付け）の検証。
 * 生の本文と replyToken が DB に残らず、ジョブには3つだけ渡ることも確かめる。
 */
class LineWebhookFlowTest extends TestCase
{
    private string $secret = 'test-channel-secret';

    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'line.channel_secret' => $this->secret,
            'line.channel_access_token' => 'test-line-token',
            'line.link_code_pepper' => 'test-pepper',
            'line.code_attempt_limit' => 5,
            'line.code_attempt_decay_seconds' => 600,
            'line.message_cache_ttl_seconds' => 120,
            'ai.gemini.api_key' => 'test-gemini-key',
            'ai.gemini.model' => 'gemini-3.5-flash-lite',
            'ai.gemini.paid_project_verified' => true,
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('line_user_id')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('company_name');
            $table->string('normalized_company_name');
            $table->string('industry');
            $table->text('job_info_text')->nullable();
            $table->string('selection_status')->default('検討中');
            $table->timestamp('research_generated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('line_one_time_codes', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('code_hash')->unique();
            $table->timestamp('expires_at');
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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('company_id')->nullable();
            $table->text('company_name')->nullable();
            $table->text('title');
            $table->text('event_type');
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->text('meeting_url')->nullable();
            $table->text('memo')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
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
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{0: string, 1: string}
     */
    private function signed(array $event): array
    {
        $body = json_encode(['events' => [$event]], JSON_THROW_ON_ERROR);
        $signature = base64_encode(hash_hmac('sha256', $body, $this->secret, true));

        return [$body, $signature];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function postEvent(array $event): void
    {
        [$body, $signature] = $this->signed($event);

        $this->call('POST', '/api/v1/line/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $signature,
        ], $body)->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function textEvent(string $text, string $eventId = 'evt-1'): array
    {
        return [
            'type' => 'message',
            'webhookEventId' => $eventId,
            'replyToken' => 'reply-1',
            'source' => ['type' => 'user', 'userId' => 'U-linked'],
            'message' => ['id' => 'msg-1', 'type' => 'text', 'text' => $text],
        ];
    }

    private function fakeLine(): void
    {
        Http::fake(['api.line.me/*' => Http::response([], 200)]);
    }

    private function replyText(): ?string
    {
        $sent = Http::recorded()->first(fn ($pair) => str_contains($pair[0]->url(), '/message/reply'));

        return $sent === null ? null : $sent[0]['messages'][0]['text'];
    }

    public function test_linked_text_is_cached_encrypted_and_dispatched_with_minimal_args(): void
    {
        Queue::fake();
        $this->fakeLine();

        $this->postEvent($this->textEvent('11月28日14時から面接です'));

        Queue::assertPushed(ParseLineMessageJob::class, 1);
        Queue::assertPushed(ParseLineMessageJob::class, function (ParseLineMessageJob $job): bool {
            $this->assertSame($this->userId, $job->userId);
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $job->lineMsgKey);
            $this->assertStringEndsWith('+09:00', $job->receivedAt);

            return true;
        });

        $job = Queue::pushed(ParseLineMessageJob::class)->first();
        $cached = Cache::get('line:msg:'.$job->lineMsgKey);

        $this->assertIsString($cached);
        $this->assertStringNotContainsString('11月28日', $cached);
        $this->assertStringContainsString('11月28日', Crypt::decryptString($cached));
        $this->assertDatabaseHas('ai_usage_logs', ['user_id' => $this->userId, 'purpose' => 'LINEのトーク解析']);
    }

    public function test_duplicate_webhook_event_is_ignored(): void
    {
        Queue::fake();
        $this->fakeLine();

        $event = $this->textEvent('11月28日14時から面接です', 'evt-duplicate');
        $this->postEvent($event);
        $this->postEvent($event);

        Queue::assertPushed(ParseLineMessageJob::class, 1);
    }

    public function test_over_limit_replies_and_does_not_dispatch(): void
    {
        Queue::fake();
        $this->fakeLine();

        for ($i = 0; $i < 20; $i++) {
            DB::table('ai_usage_logs')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->userId,
                'model_name' => 'test',
                'used_at' => now(),
                'purpose' => '業界の候補',
            ]);
        }

        $this->postEvent($this->textEvent('11月28日14時から面接です'));

        Queue::assertNothingPushed();
        $this->assertSame('読み取りの回数の上限に達しました。時間をおいて送るか、Webのカレンダーから登録してください', $this->replyText());
    }

    public function test_valid_code_links_the_user(): void
    {
        $this->fakeLine();
        User::query()->whereKey($this->userId)->update(['line_user_id' => null]);

        LineOneTimeCode::create([
            'user_id' => $this->userId,
            'code_hash' => hash_hmac('sha256', '123456', 'test-pepper'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postEvent($this->textEvent('123456'));

        $this->assertSame('U-linked', User::query()->find($this->userId)->line_user_id);
        $this->assertDatabaseMissing('line_one_time_codes', ['user_id' => $this->userId]);
        $this->assertSame('連携が完了しました。今後、選考予定と締切をお知らせします。', $this->replyText());
    }

    public function test_invalid_code_does_not_link(): void
    {
        $this->fakeLine();
        User::query()->whereKey($this->userId)->update(['line_user_id' => null]);

        $this->postEvent($this->textEvent('123456'));

        $this->assertNull(User::query()->find($this->userId)->line_user_id);
        $this->assertSame('連携コードが正しくないか、有効期限が切れています', $this->replyText());
    }

    public function test_unlinked_non_code_asks_for_linking(): void
    {
        $this->fakeLine();
        User::query()->whereKey($this->userId)->update(['line_user_id' => null]);

        $this->postEvent($this->textEvent('こんにちは'));

        $this->assertStringStartsWith('ShuPortとの連携が完了していません', $this->replyText() ?? '');
    }

    public function test_yes_registers_the_event_and_deletes_pending(): void
    {
        $this->fakeLine();
        $companyId = (string) Str::uuid();

        DB::table('companies')->insert([
            'id' => $companyId,
            'user_id' => $this->userId,
            'company_name' => '株式会社テスト',
            'normalized_company_name' => '株式会社テスト',
            'industry' => 'IT・ソフトウェア・通信',
            'selection_status' => '検討中',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pending_line_events')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'company_name' => '株式会社テスト',
            'event_type' => '面接',
            'start_at' => '2026-11-28 14:00:00+09',
            'end_at' => '2026-11-28 15:00:00+09',
            'meeting_url' => 'https://example.com/meet',
            'memo' => '履歴書',
            'expires_at' => now()->addDay(),
        ]);

        $this->postEvent($this->textEvent('はい'));

        $this->assertDatabaseCount('pending_line_events', 0);
        $this->assertDatabaseHas('events', [
            'user_id' => $this->userId,
            'company_id' => $companyId,
            'title' => '株式会社テスト 面接',
            'event_type' => '面接',
        ]);
        $this->assertSame('予定を登録しました', $this->replyText());
    }

    public function test_no_deletes_pending_and_does_not_create_event(): void
    {
        $this->fakeLine();

        DB::table('pending_line_events')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'company_name' => '株式会社テスト',
            'event_type' => '面接',
            'start_at' => '2026-11-28 14:00:00+09',
            'end_at' => null,
            'meeting_url' => null,
            'memo' => null,
            'expires_at' => now()->addDay(),
        ]);

        $this->postEvent($this->textEvent('いいえ'));

        $this->assertDatabaseCount('pending_line_events', 0);
        $this->assertDatabaseCount('events', 0);
        $this->assertSame('登録を取り消しました', $this->replyText());
    }

    public function test_expired_confirmation_replies_expired(): void
    {
        $this->fakeLine();

        DB::table('pending_line_events')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'company_name' => null,
            'event_type' => '面接',
            'start_at' => '2026-11-28 14:00:00+09',
            'end_at' => null,
            'meeting_url' => null,
            'memo' => null,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postEvent($this->textEvent('はい'));

        $this->assertSame('確認の有効期限が切れました。もう一度送信してください', $this->replyText());
    }

    public function test_ai_unavailable_replies_suspended_and_does_not_dispatch(): void
    {
        Queue::fake();
        $this->fakeLine();
        config(['ai.gemini.api_key' => '', 'ai.gemini.paid_project_verified' => false, 'ai.workers_ai.account_id' => '', 'ai.workers_ai.api_token' => '']);

        $this->postEvent($this->textEvent('11月28日14時から面接です'));

        Queue::assertNothingPushed();
        $this->assertSame('ただいま日程の自動読み取りを停止しています。Webのカレンダーから登録してください', $this->replyText());
    }

    public function test_non_text_message_is_ignored(): void
    {
        $this->fakeLine();

        $event = $this->textEvent('ignored');
        $event['message'] = ['id' => 'msg-2', 'type' => 'sticker'];

        $this->postEvent($event);

        Http::assertNothingSent();
    }
}
