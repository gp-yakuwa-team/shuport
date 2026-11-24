<?php

namespace Tests\Feature\Api;

use App\Models\AiJob;
use App\Models\EntrySheet;
use App\Support\Supabase\JwtVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * ES の作成・生成（API-08・13）とジョブ進捗（API-11）の検証。
 * SQLite に必要最小限の表を作り、JwtVerifier をモックする。
 */
class EntrySheetsApiTest extends TestCase
{
    private string $userId;

    private string $otherUserId;

    private string $companyId;

    private string $otherCompanyId;

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
        });
        Schema::create('entry_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('title');
            $table->timestamps();
        });
        Schema::create('es_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('entry_sheet_id');
            $table->string('item_type');
            $table->text('question');
            $table->text('answer')->nullable();
            $table->integer('max_chars');
            $table->integer('sort_order');
            $table->text('ai_draft')->nullable();
            $table->timestamps();
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
        $this->companyId = (string) Str::uuid();
        $this->otherCompanyId = (string) Str::uuid();

        foreach ([$this->userId, $this->otherUserId] as $id) {
            DB::table('users')->insert([
                'id' => $id, 'is_demo' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('companies')->insert([
            [
                'id' => $this->companyId, 'user_id' => $this->userId,
                'company_name' => '株式会社テスト', 'normalized_company_name' => '株式会社テスト',
                'industry' => 'IT・ソフトウェア・通信', 'selection_status' => '検討中',
                'research_generated_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => $this->otherCompanyId, 'user_id' => $this->otherUserId,
                'company_name' => '株式会社タニン', 'normalized_company_name' => '株式会社タニン',
                'industry' => '商社', 'selection_status' => '検討中',
                'research_generated_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $verifier = Mockery::mock(JwtVerifier::class);
        $verifier->shouldReceive('verify')->andReturnUsing(
            fn (string $token) => (object) ['sub' => $token]
        );
        $this->app->instance(JwtVerifier::class, $verifier);

        config([
            'ai.gemini.api_key' => 'test-key',
            'ai.gemini.paid_project_verified' => true,
        ]);

        Queue::fake();
    }

    private function headers(string $userId): array
    {
        return ['Authorization' => 'Bearer '.$userId];
    }

    private function payload(string $companyId): array
    {
        return [
            'company_id' => $companyId,
            'title' => '本選考用ES',
            'questions' => [
                ['order' => 1, 'type' => 'motivation', 'question_text' => '当社を志望する理由を教えてください。', 'max_chars' => 400],
                ['order' => 2, 'type' => 'self_pr', 'question_text' => '自己PRをご記入ください。', 'max_chars' => 400],
            ],
        ];
    }

    public function test_store_creates_es_answers_and_starts_generation_and_research(): void
    {
        $response = $this->postJson('/api/v1/entry-sheets', $this->payload($this->companyId), $this->headers($this->userId));

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'processing');
        $this->assertNotNull($response->json('data.generation_job_id'));
        // 企業研究の結果がない企業なので、企業研究も連動して開始する
        $this->assertNotNull($response->json('data.research_job_id'));

        $this->assertDatabaseHas('entry_sheets', ['title' => '本選考用ES']);
        $this->assertDatabaseHas('es_answers', ['item_type' => '志望動機', 'max_chars' => 400]);
        $this->assertDatabaseHas('es_answers', ['item_type' => '自己PR']);
        $this->assertDatabaseHas('ai_jobs', ['job_type' => 'ES生成', 'status' => '未処理']);
        $this->assertDatabaseHas('ai_jobs', ['job_type' => '企業研究', 'status' => '未処理']);
        $this->assertDatabaseHas('ai_usage_logs', ['purpose' => 'ES生成']);
    }

    public function test_store_for_other_users_company_is_404(): void
    {
        $response = $this->postJson('/api/v1/entry-sheets', $this->payload($this->otherCompanyId), $this->headers($this->userId));

        $response->assertStatus(404);
    }

    public function test_store_rejects_invalid_questions(): void
    {
        $bad = $this->payload($this->companyId);
        $bad['questions'][0]['max_chars'] = 2001;

        $this->postJson('/api/v1/entry-sheets', $bad, $this->headers($this->userId))->assertStatus(422);

        $dup = $this->payload($this->companyId);
        $dup['questions'][1]['order'] = 1;

        $this->postJson('/api/v1/entry-sheets', $dup, $this->headers($this->userId))->assertStatus(422);
    }

    public function test_generate_starts_and_conflicts_while_in_flight(): void
    {
        $entrySheet = EntrySheet::create(['company_id' => $this->companyId, 'title' => '下書きES']);

        $first = $this->postJson("/api/v1/entry-sheets/{$entrySheet->id}/generate", [], $this->headers($this->userId));
        $first->assertStatus(202);
        $first->assertJsonPath('data.status', 'processing');

        // 本番（セッション TZ = JST）では DB 既定の now() が JST で入る。SQLite は UTC のためそろえる
        DB::table('ai_jobs')->update(['created_at' => now()]);

        $second = $this->postJson("/api/v1/entry-sheets/{$entrySheet->id}/generate", [], $this->headers($this->userId));
        $second->assertStatus(409);
    }

    public function test_generate_at_generation_limit_returns_429(): void
    {
        $entrySheet = EntrySheet::create(['company_id' => $this->companyId, 'title' => '下書きES']);

        for ($i = 0; $i < 20; $i++) {
            DB::table('ai_usage_logs')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->userId,
                'model_name' => 'test',
                'used_at' => now(),
                'purpose' => 'ES生成',
            ]);
        }

        $response = $this->postJson("/api/v1/entry-sheets/{$entrySheet->id}/generate", [], $this->headers($this->userId));

        $response->assertStatus(429);
        $response->assertJsonPath('detail', '1時間あたりのAI生成の上限に達しました。時間をおいて再度お試しください');
    }

    public function test_job_show_maps_status_and_type(): void
    {
        $job = AiJob::create([
            'job_type' => 'ES生成',
            'company_id' => $this->companyId,
            'entry_sheet_id' => null,
            'status' => '完了',
            'progress_step' => 4,
        ]);
        DB::table('ai_jobs')->where('id', $job->id)->update(['created_at' => now()]);

        $response = $this->getJson("/api/v1/jobs/{$job->id}", $this->headers($this->userId));

        $response->assertStatus(200);
        $response->assertJsonPath('data.job_type', 'generate_es_draft');
        $response->assertJsonPath('data.status', 'succeeded');
        $response->assertJsonPath('data.progress_step', 4);
    }

    public function test_job_show_treats_stale_job_as_failed(): void
    {
        $job = AiJob::create([
            'job_type' => '企業研究',
            'company_id' => $this->companyId,
            'status' => '未処理',
        ]);
        DB::table('ai_jobs')->where('id', $job->id)->update(['created_at' => now()->subMinutes(10)]);

        $response = $this->getJson("/api/v1/jobs/{$job->id}", $this->headers($this->userId));

        $response->assertJsonPath('data.status', 'failed');
    }

    public function test_job_show_for_other_users_job_is_404(): void
    {
        $job = AiJob::create([
            'job_type' => '企業研究',
            'company_id' => $this->otherCompanyId,
            'status' => '完了',
        ]);

        $response = $this->getJson("/api/v1/jobs/{$job->id}", $this->headers($this->userId));

        $response->assertStatus(404);
    }
}
