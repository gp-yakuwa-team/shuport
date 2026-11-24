<?php

namespace Tests\Feature\Api;

use App\Jobs\ResearchCompanyJob;
use App\Models\User;
use App\Support\Supabase\JwtVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 企業まわりの API（API-07・09・17・18・19・20・21・22・23・41）。
 * 本番 migration は PostgreSQL 専用のため、テストでは SQLite に必要最小限の表を作る。
 */
class CompaniesApiTest extends TestCase
{
    private string $userId;

    private string $otherUserId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->createSchema();

        $this->userId = (string) Str::uuid();
        $this->otherUserId = (string) Str::uuid();
        User::forceCreate(['id' => $this->userId, 'is_demo' => false]);
        User::forceCreate(['id' => $this->otherUserId, 'is_demo' => false]);

        $verifier = $this->mock(JwtVerifier::class);
        $verifier->shouldReceive('verify')->andReturn((object) ['sub' => $this->userId]);
        $verifier->shouldReceive('sessionExists')->andReturn(true);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('line_user_id')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('companies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->text('company_name');
            $table->text('normalized_company_name');
            $table->text('industry');
            $table->text('job_info_text')->nullable();
            $table->text('selection_status')->default('検討中');
            $table->text('research_business_overview')->nullable();
            $table->text('research_strengths')->nullable();
            $table->text('research_weaknesses')->nullable();
            $table->text('research_selection_flow')->nullable();
            $table->text('research_interview_questions')->nullable();
            $table->text('research_latest_topics')->nullable();
            $table->timestamp('research_generated_at')->nullable();
            $table->timestamp('topics_fetched_at')->nullable();
            $table->text('search_suggestions_html')->nullable();
            $table->text('source_links')->nullable();
            $table->text('topics_search_suggestions_html')->nullable();
            $table->text('topics_source_links')->nullable();
            $table->timestamps();
        });
        Schema::create('company_login_infos', function (Blueprint $table) {
            $table->string('company_id')->primary();
            $table->text('login_url')->nullable();
            $table->text('login_id_encrypted')->nullable();
            $table->text('password_encrypted')->nullable();
        });
        Schema::create('selection_status_histories', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('company_id');
            $table->text('from_status')->nullable();
            $table->text('to_status');
            $table->timestamp('changed_at')->nullable();
        });
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->text('job_type');
            $table->string('company_id')->nullable();
            $table->string('entry_sheet_id')->nullable();
            $table->text('status')->default('未処理');
            $table->integer('progress_step')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->text('model_name');
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->timestamp('used_at');
            $table->text('purpose');
        });
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer test-token'];
    }

    private function createCompany(array $attributes = []): string
    {
        $response = $this->postJson('/api/v1/companies', array_merge([
            'company_name' => 'テスト株式会社',
            'industry' => 'IT・ソフトウェア・通信',
            'job_info_text' => '募集要項の本文',
        ], $attributes), $this->headers());
        $this->assertContains($response->status(), [201, 202]);

        return $response->json('company_id');
    }

    public function test_store_returns_201_without_research_when_ai_is_unavailable(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/companies', [
            'company_name' => 'テスト株式会社',
            'industry' => 'IT・ソフトウェア・通信',
        ], $this->headers());

        $response->assertStatus(201);
        $response->assertJsonPath('research_job_status', 'skipped_ai_unavailable');
        $this->assertDatabaseHas('companies', ['company_name' => 'テスト株式会社']);
        Queue::assertNotPushed(ResearchCompanyJob::class);
    }

    public function test_store_dispatches_research_and_returns_202_when_ai_is_available(): void
    {
        Queue::fake();
        config([
            'ai.gemini.api_key' => 'test-key',
            'ai.gemini.paid_project_verified' => true,
        ]);

        $response = $this->postJson('/api/v1/companies', [
            'company_name' => 'テスト株式会社',
            'industry' => 'IT・ソフトウェア・通信',
        ], $this->headers());

        $response->assertStatus(202);
        $response->assertJsonPath('research_job_status', 'researching');
        Queue::assertPushed(ResearchCompanyJob::class);
        $this->assertDatabaseHas('ai_jobs', ['job_type' => '企業研究', 'status' => '未処理']);
        $this->assertDatabaseHas('ai_usage_logs', ['purpose' => '企業研究']);
    }

    public function test_store_returns_409_for_a_duplicate_company_name(): void
    {
        $this->createCompany();

        $response = $this->postJson('/api/v1/companies', [
            'company_name' => ' テスト株式会社 ',
            'industry' => '商社',
        ], $this->headers());

        $response->assertStatus(409);
    }

    public function test_store_rejects_an_unknown_industry(): void
    {
        $response = $this->postJson('/api/v1/companies', [
            'company_name' => 'テスト株式会社',
            'industry' => '存在しない業界',
        ], $this->headers());

        $response->assertStatus(422);
    }

    public function test_index_returns_only_the_users_companies(): void
    {
        $this->createCompany();
        DB::table('companies')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->otherUserId,
            'company_name' => '他人の会社',
            'normalized_company_name' => '他人の会社',
            'industry' => '商社',
            'selection_status' => '検討中',
        ]);

        $response = $this->getJson('/api/v1/companies', $this->headers());

        $response->assertOk();
        $response->assertJsonCount(1, 'companies');
        $response->assertJsonPath('companies.0.company_name', 'テスト株式会社');
    }

    public function test_show_returns_404_for_another_users_company(): void
    {
        $companyId = $this->createCompany();

        $verifier = $this->mock(JwtVerifier::class);
        $verifier->shouldReceive('verify')->andReturn((object) ['sub' => $this->otherUserId]);

        $response = $this->getJson("/api/v1/companies/{$companyId}", $this->headers());

        $response->assertStatus(404);
    }

    public function test_update_status_records_a_history_row(): void
    {
        $companyId = $this->createCompany();

        $response = $this->patchJson("/api/v1/companies/{$companyId}/status", [
            'selection_status' => '一次面接',
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonPath('selection_status', '一次面接');
        $this->assertDatabaseHas('selection_status_histories', [
            'company_id' => $companyId,
            'from_status' => '検討中',
            'to_status' => '一次面接',
        ]);
    }

    public function test_update_status_rejects_an_unknown_status(): void
    {
        $companyId = $this->createCompany();

        $response = $this->patchJson("/api/v1/companies/{$companyId}/status", [
            'selection_status' => '存在しない状態',
        ], $this->headers());

        $response->assertStatus(422);
    }

    public function test_login_info_is_encrypted_and_the_password_can_be_revealed(): void
    {
        $companyId = $this->createCompany();

        $response = $this->putJson("/api/v1/companies/{$companyId}/login-info", [
            'login_url' => 'https://example.com/login',
            'login_id' => 'my-id',
            'password' => 'my-secret',
        ], $this->headers());
        $response->assertOk();

        $row = DB::table('company_login_infos')->where('company_id', $companyId)->first();
        $this->assertNotSame('my-secret', $row->password_encrypted);
        $this->assertNotSame('my-id', $row->login_id_encrypted);

        $response = $this->getJson("/api/v1/companies/{$companyId}/login-info", $this->headers());
        $response->assertOk();
        $response->assertJsonPath('login_id', 'my-id');
        $this->assertArrayNotHasKey('password', $response->json());

        $response = $this->postJson("/api/v1/companies/{$companyId}/login-info/password", [], $this->headers());
        $response->assertOk();
        $response->assertJsonPath('password', 'my-secret');
    }

    public function test_destroy_deletes_the_company(): void
    {
        $companyId = $this->createCompany();

        $response = $this->deleteJson("/api/v1/companies/{$companyId}", [], $this->headers());

        $response->assertStatus(204);
        $this->assertDatabaseMissing('companies', ['id' => $companyId]);
    }

    public function test_research_returns_the_same_job_while_processing(): void
    {
        $companyId = $this->createCompany();
        $jobId = (string) Str::uuid();
        DB::table('ai_jobs')->insert([
            'id' => $jobId,
            'job_type' => '企業研究',
            'company_id' => $companyId,
            'status' => '生成中',
            'created_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/companies/{$companyId}/research", [], $this->headers());

        $response->assertStatus(202);
        $response->assertJsonPath('job_id', $jobId);
        $this->assertSame(1, DB::table('ai_jobs')->count());
    }

    public function test_industry_suggestion_returns_null_without_a_prompt_file(): void
    {
        $response = $this->postJson('/api/v1/companies/industry-suggestion', [
            'company_name' => 'テスト株式会社',
        ], $this->headers());

        $response->assertOk();
        $response->assertJsonPath('industry', null);
    }
}
