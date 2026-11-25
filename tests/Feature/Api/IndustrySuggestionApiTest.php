<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Supabase\JwtVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * API-17 業界の候補（P-04）の検証。
 * 成功・分類不能・AI の失敗・上限のいずれも、画面を妨げない形で返すことを確かめる。
 */
class IndustrySuggestionApiTest extends TestCase
{
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();

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
            'terms_accepted_at' => now(),
            'terms_version' => 'v1',
            'is_demo' => false,
        ]);

        $verifier = Mockery::mock(JwtVerifier::class);
        $verifier->shouldReceive('verify')->andReturnUsing(
            fn (string $token) => (object) ['sub' => $token]
        );
        $this->app->instance(JwtVerifier::class, $verifier);

        config([
            'ai.gemini.api_key' => 'test-gemini-key',
            'ai.gemini.model' => 'gemini-3.5-flash-lite',
            'ai.gemini.paid_project_verified' => true,
            'ai.workers_ai.account_id' => 'test-account',
            'ai.workers_ai.api_token' => 'test-cf-token',
            'ai.workers_ai.model' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
        ]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->userId];
    }

    public function test_returns_industry_from_gemini(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '{"industry":"IT・ソフトウェア・通信"}']]]],
                ],
                'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 8],
            ]),
        ]);

        $response = $this->postJson('/api/v1/companies/industry-suggestion', [
            'company_name' => '株式会社サイボウズ',
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonPath('data.industry', 'IT・ソフトウェア・通信');
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $this->userId,
            'purpose' => '業界の候補',
            'model_name' => 'gemini-3.5-flash-lite',
            'input_tokens' => 120,
            'output_tokens' => 8,
        ]);
        Http::assertSent(function ($request) {
            $text = $request['contents'][0]['parts'][0]['text'] ?? '';

            return is_string($text) && str_contains($text, '"task":"industry_suggestion"');
        });
    }

    public function test_returns_null_when_ai_fails(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.cloudflare.com/*' => Http::response([], 500),
        ]);

        $response = $this->postJson('/api/v1/companies/industry-suggestion', [
            'company_name' => '株式会社テスト',
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonPath('data.industry', null);
        $this->assertDatabaseHas('error_logs', ['error_type' => '外部サービス']);
        $this->assertDatabaseMissing('ai_usage_logs', ['purpose' => '業界の候補']);
    }

    public function test_falls_back_to_workers_ai_and_records_its_model(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.cloudflare.com/*' => Http::response([
                'result' => ['response' => '{"industry":"商社"}'],
            ]),
        ]);

        $response = $this->postJson('/api/v1/companies/industry-suggestion', [
            'company_name' => '株式会社テスト',
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonPath('data.industry', '商社');
        $this->assertDatabaseHas('ai_usage_logs', [
            'purpose' => '業界の候補',
            'model_name' => '@cf/meta/llama-3.3-70b-instruct-fp8-fast',
        ]);
    }

    public function test_returns_null_when_industry_is_not_one_of_the_17(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => '{"industry":"宇宙開発"}']]]],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/v1/companies/industry-suggestion', [
            'company_name' => '株式会社テスト',
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonPath('data.industry', null);
        $this->assertDatabaseMissing('ai_usage_logs', ['purpose' => '業界の候補']);
    }

    public function test_returns_null_at_generation_limit_without_calling_ai(): void
    {
        Http::fake();

        for ($i = 0; $i < 20; $i++) {
            DB::table('ai_usage_logs')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->userId,
                'model_name' => 'test',
                'used_at' => now(),
                'purpose' => '業界の候補',
            ]);
        }

        $response = $this->postJson('/api/v1/companies/industry-suggestion', [
            'company_name' => '株式会社テスト',
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonPath('data.industry', null);
        Http::assertNothingSent();
    }

    public function test_validates_company_name(): void
    {
        $this->postJson('/api/v1/companies/industry-suggestion', [], $this->headers())
            ->assertStatus(422);

        $this->postJson('/api/v1/companies/industry-suggestion', [
            'company_name' => str_repeat('あ', 101),
        ], $this->headers())->assertStatus(422);
    }
}
