<?php

namespace Tests\Feature\Api;

use App\Models\AiJob;
use App\Models\Company;
use App\Support\Supabase\JwtVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * 企業まわりの API のうち、API定義書 v18 で項目が確定しているもの
 * （API-07・09・10・41）の検証。SQLite に必要最小限の表を作り、
 * JwtVerifier をモックして認証済みリクエストを再現する。
 */
class CompaniesCoreApiTest extends TestCase
{
    private string $userId;

    private string $otherUserId;

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
            $table->unique(['user_id', 'normalized_company_name']);
        });
        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('job_type');
            $table->uuid('company_id')->nullable();
            $table->uuid('entry_sheet_id')->nullable();
            $table->string('status')->default('未処理');
            $table->integer('progress_step')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
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

        $this->userId = (string) Str::uuid();
        $this->otherUserId = (string) Str::uuid();

        foreach ([$this->userId, $this->otherUserId] as $id) {
            DB::table('users')->insert([
                'id' => $id,
                'is_demo' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $verifier = Mockery::mock(JwtVerifier::class);
        $verifier->shouldReceive('verify')->andReturnUsing(
            fn (string $token) => (object) ['sub' => $token]
        );
        $this->app->instance(JwtVerifier::class, $verifier);

        Queue::fake();
    }

    private function headers(string $userId): array
    {
        return ['Authorization' => 'Bearer '.$userId];
    }

    private function geminiAvailable(): void
    {
        config([
            'ai.gemini.api_key' => 'test-key',
            'ai.gemini.paid_project_verified' => true,
        ]);
    }

    private function makeCompany(string $userId, string $name = '株式会社テスト'): Company
    {
        return Company::create([
            'user_id' => $userId,
            'company_name' => $name,
            'normalized_company_name' => Company::normalizeName($name),
            'industry' => 'IT・ソフトウェア・通信',
            'selection_status' => '検討中',
        ]);
    }

    public function test_store_starts_research_and_returns_202(): void
    {
        $this->geminiAvailable();

        $response = $this->postJson('/api/v1/companies', [
            'name' => '株式会社サイボウズ',
            'industry' => 'IT・ソフトウェア・通信',
            'job_description' => '募集要項',
        ], $this->headers($this->userId));

        $response->assertStatus(202);
        $response->assertJsonPath('data.research_job_status', 'queued');
        $response->assertJsonPath('data.name', '株式会社サイボウズ');
        $this->assertNotNull($response->json('data.job_id'));
        $this->assertDatabaseHas('companies', ['company_name' => '株式会社サイボウズ']);
        $this->assertDatabaseHas('ai_jobs', ['job_type' => '企業研究', 'status' => '未処理']);
        $this->assertDatabaseHas('ai_usage_logs', ['purpose' => '企業研究']);
    }

    public function test_store_duplicate_returns_409_with_company_id(): void
    {
        $this->geminiAvailable();
        $company = $this->makeCompany($this->userId);

        $response = $this->postJson('/api/v1/companies', [
            'name' => '株式会社テスト',
            'industry' => '商社',
        ], $this->headers($this->userId));

        $response->assertStatus(409);
        $response->assertJsonPath('detail', 'この企業はすでに登録されています');
        $response->assertJsonPath('company_id', $company->id);
    }

    public function test_store_at_research_limit_registers_only(): void
    {
        $this->geminiAvailable();

        for ($i = 0; $i < 20; $i++) {
            DB::table('ai_usage_logs')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->userId,
                'model_name' => 'test',
                // AiUsageGate は used_at を UTC の時刻として数える（本番は timestamptz）。
                'used_at' => now('UTC'),
                'purpose' => '企業研究',
            ]);
        }

        $response = $this->postJson('/api/v1/companies', [
            'name' => '株式会社リミット',
            'industry' => '商社',
        ], $this->headers($this->userId));

        $response->assertStatus(201);
        $response->assertJsonPath('data.research_job_status', 'limit_reached');
        $response->assertJsonPath('data.job_id', null);
        $this->assertDatabaseHas('companies', ['company_name' => '株式会社リミット']);
        $this->assertDatabaseMissing('ai_jobs', ['job_type' => '企業研究']);
    }

    public function test_store_when_ai_unavailable_creates_failed_job_row(): void
    {
        config(['ai.gemini.api_key' => null]);

        $response = $this->postJson('/api/v1/companies', [
            'name' => '株式会社オフライン',
            'industry' => '商社',
        ], $this->headers($this->userId));

        $response->assertStatus(201);
        $response->assertJsonPath('data.research_job_status', 'skipped_ai_unavailable');
        $this->assertDatabaseHas('ai_jobs', ['job_type' => '企業研究', 'status' => '失敗']);
        $this->assertDatabaseMissing('ai_usage_logs', ['purpose' => '企業研究']);
    }

    public function test_store_rejects_unknown_industry(): void
    {
        $response = $this->postJson('/api/v1/companies', [
            'name' => '株式会社テスト',
            'industry' => '宇宙産業',
        ], $this->headers($this->userId));

        $response->assertStatus(422);
    }

    public function test_research_returns_same_job_while_in_flight(): void
    {
        $this->geminiAvailable();
        $company = $this->makeCompany($this->userId);

        $first = $this->postJson("/api/v1/companies/{$company->id}/research", [], $this->headers($this->userId));
        $first->assertStatus(202);

        // 本番（セッション TZ = JST）では DB 既定の now() が JST で入る。
        // SQLite の CURRENT_TIMESTAMP は UTC のため、作成時刻をアプリ時刻にそろえる。
        DB::table('ai_jobs')->update(['created_at' => now()]);

        $second = $this->postJson("/api/v1/companies/{$company->id}/research", [], $this->headers($this->userId));
        $second->assertStatus(202);
        $second->assertJsonPath('data.job_id', $first->json('data.job_id'));
        $this->assertSame(1, AiJob::count());
    }

    public function test_research_for_other_users_company_is_404(): void
    {
        $company = $this->makeCompany($this->otherUserId);

        $response = $this->postJson("/api/v1/companies/{$company->id}/research", [], $this->headers($this->userId));

        $response->assertStatus(404);
    }

    public function test_topics_refresh_when_ai_unavailable_returns_200_unavailable(): void
    {
        config(['ai.gemini.api_key' => null]);
        $company = $this->makeCompany($this->userId);

        $response = $this->postJson("/api/v1/companies/{$company->id}/topics/refresh", [], $this->headers($this->userId));

        $response->assertStatus(200);
        $response->assertJsonPath('data.topics_status', 'unavailable');
        $response->assertJsonPath('data.job_id', null);
    }

    public function test_destroy_deletes_own_company(): void
    {
        $company = $this->makeCompany($this->userId);

        $response = $this->deleteJson("/api/v1/companies/{$company->id}", [], $this->headers($this->userId));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }
}
