<?php

namespace Tests\Feature\Api;

use App\Models\AiJob;
use App\Models\Company;
use App\Support\Supabase\JwtVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * API-19 企業詳細取得（詳細設計書 v5 付録A A.4）と、
 * 状態の判定を共通にした後の API-11 の検証。
 * SQLite に必要最小限の表を作り、JwtVerifier をモックして認証済みリクエストを再現する。
 */
class CompanyDetailApiTest extends TestCase
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
            $table->text('research_business_overview')->nullable();
            $table->text('research_strengths')->nullable();
            $table->text('research_weaknesses')->nullable();
            $table->text('research_selection_flow')->nullable();
            $table->text('research_interview_questions')->nullable();
            $table->text('research_latest_topics')->nullable();
            $table->timestamp('research_generated_at')->nullable();
            $table->timestamp('topics_fetched_at')->nullable();
            $table->text('search_suggestions_html')->nullable();
            $table->json('source_links')->nullable();
            $table->text('topics_search_suggestions_html')->nullable();
            $table->json('topics_source_links')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'normalized_company_name']);
        });
        Schema::create('company_login_infos', function (Blueprint $table) {
            $table->uuid('company_id')->primary();
            $table->text('login_url')->nullable();
            $table->text('login_id_encrypted')->nullable();
            $table->text('password_encrypted')->nullable();
        });
        Schema::create('selection_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->timestamp('changed_at')->useCurrent();
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
    }

    private function headers(string $userId): array
    {
        return ['Authorization' => 'Bearer '.$userId];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCompany(string $userId, array $attributes = []): Company
    {
        return Company::create($attributes + [
            'user_id' => $userId,
            'company_name' => '株式会社テスト',
            'normalized_company_name' => '株式会社テスト',
            'industry' => 'IT・ソフトウェア・通信',
            'job_info_text' => '募集要項の原文',
            'selection_status' => '検討中',
        ]);
    }

    private function makeJob(string $companyId, string $status, Carbon $createdAt, string $jobType = '企業研究'): AiJob
    {
        $job = AiJob::create([
            'job_type' => $jobType,
            'company_id' => $companyId,
            'status' => $status,
        ]);
        DB::table('ai_jobs')->where('id', $job->id)->update(['created_at' => $createdAt->format('Y-m-d H:i:s')]);

        return $job;
    }

    private function addHistory(string $companyId, ?string $from, string $to, string $changedAt, ?string $id = null): string
    {
        $id ??= (string) Str::uuid();
        DB::table('selection_status_histories')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'from_status' => $from,
            'to_status' => $to,
            'changed_at' => $changedAt,
        ]);

        return $id;
    }

    private function addEntrySheet(string $companyId, string $title, string $updatedAt, ?string $id = null): string
    {
        $id ??= (string) Str::uuid();
        DB::table('entry_sheets')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'title' => $title,
            'created_at' => '2026-11-01 09:00:00',
            'updated_at' => $updatedAt,
        ]);

        return $id;
    }

    public function test_show_returns_all_fields(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00', 'Asia/Tokyo'));

        $company = $this->makeCompany($this->userId, [
            'selection_status' => 'エントリー済み',
            'research_business_overview' => '事業概要',
            'research_strengths' => '強み',
            'research_weaknesses' => '弱み',
            'research_selection_flow' => '選考フロー',
            'research_interview_questions' => '想定質問',
            'research_latest_topics' => 'トピックス',
            'research_generated_at' => now(),
            'topics_fetched_at' => now()->addHour(),
            'search_suggestions_html' => '<div>候補</div>',
            'source_links' => ['https://example.com/a'],
            'topics_search_suggestions_html' => '<div>トピックス候補</div>',
            'topics_source_links' => ['https://example.com/t'],
        ]);
        $historyId = $this->addHistory($company->id, null, '検討中', '2026-11-20 10:00:00');
        $this->makeJob($company->id, '完了', now());
        DB::table('company_login_infos')->insert(['company_id' => $company->id, 'login_url' => 'https://example.com']);

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $response->assertStatus(200);
        $response->assertExactJson(['data' => [
            'id' => $company->id,
            'company_name' => '株式会社テスト',
            'industry' => 'IT・ソフトウェア・通信',
            'job_info_text' => '募集要項の原文',
            'selection_status' => 'エントリー済み',
            'research' => [
                'business_overview' => '事業概要',
                'strengths' => '強み',
                'weaknesses' => '弱み',
                'selection_flow' => '選考フロー',
                'interview_questions' => '想定質問',
                'latest_topics' => 'トピックス',
                'generated_at' => '2026-11-20T10:00:00+09:00',
                'source_links' => ['https://example.com/a'],
                'search_suggestions_html' => '<div>候補</div>',
            ],
            'topics' => [
                'search_suggestions_html' => '<div>トピックス候補</div>',
                'source_links' => ['https://example.com/t'],
                'fetched_at' => '2026-11-20T11:00:00+09:00',
            ],
            'research_status' => 'succeeded',
            'status_histories' => [
                ['id' => $historyId, 'from_status' => null, 'to_status' => '検討中', 'changed_at' => '2026-11-20T10:00:00+09:00'],
            ],
            'entry_sheets' => [],
            'has_login_info' => true,
            'created_at' => '2026-11-20T10:00:00+09:00',
            'updated_at' => '2026-11-20T10:00:00+09:00',
        ]]);
        $this->assertArrayNotHasKey('login_url', $response->json('data'));
    }

    public function test_show_without_research_returns_nulls(): void
    {
        $company = $this->makeCompany($this->userId);

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $response->assertStatus(200);
        $response->assertJsonPath('data.research', null);
        $response->assertJsonPath('data.topics', null);
        $response->assertJsonPath('data.research_status', null);
        $response->assertJsonPath('data.status_histories', []);
        $response->assertJsonPath('data.has_login_info', false);
        // キーを省略せずに null を返す（A.1）
        $this->assertArrayHasKey('research', $response->json('data'));
        $this->assertArrayHasKey('topics', $response->json('data'));
        $this->assertArrayHasKey('research_status', $response->json('data'));
    }

    public function test_show_topics_has_null_fields_before_first_refresh(): void
    {
        // 最初の企業研究で topics_fetched_at も入る（4.10 BG-01）。再取得前は候補・出典が空
        $company = $this->makeCompany($this->userId, [
            'research_generated_at' => now(),
            'topics_fetched_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $response->assertJsonPath('data.topics.search_suggestions_html', null);
        $response->assertJsonPath('data.topics.source_links', null);
        $this->assertNotNull($response->json('data.topics.fetched_at'));
    }

    public function test_show_without_token_returns_401(): void
    {
        $company = $this->makeCompany($this->userId);

        $response = $this->getJson("/api/v1/companies/{$company->id}");

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_show_for_other_users_company_is_404(): void
    {
        $company = $this->makeCompany($this->otherUserId);

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $response->assertJsonPath('status', 404);
    }

    public function test_show_for_missing_company_is_404(): void
    {
        $response = $this->getJson('/api/v1/companies/'.Str::uuid(), $this->headers($this->userId));

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_status_histories_are_ordered_by_changed_at_desc_then_id_asc(): void
    {
        $company = $this->makeCompany($this->userId);
        $oldest = $this->addHistory($company->id, null, '検討中', '2026-11-10 09:00:00');
        $newest = $this->addHistory($company->id, 'エントリー済み', '書類選考中', '2026-11-12 09:00:00');
        $sameB = $this->addHistory($company->id, '検討中', 'エントリー済み', '2026-11-11 09:00:00', 'bbbbbbbb-0000-4000-8000-000000000000');
        $sameA = $this->addHistory($company->id, '検討中', 'エントリー済み', '2026-11-11 09:00:00', 'aaaaaaaa-0000-4000-8000-000000000000');

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $this->assertSame(
            [$newest, $sameA, $sameB, $oldest],
            array_column($response->json('data.status_histories'), 'id'),
        );
    }

    public function test_dates_are_jst_with_offset(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 23:30:00', 'Asia/Tokyo'));
        $company = $this->makeCompany($this->userId, [
            'research_generated_at' => now(),
            'topics_fetched_at' => now(),
        ]);
        $this->addHistory($company->id, null, '検討中', '2026-11-20 23:30:00');

        $data = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId))->json('data');

        foreach ([
            $data['created_at'],
            $data['updated_at'],
            $data['research']['generated_at'],
            $data['topics']['fetched_at'],
            $data['status_histories'][0]['changed_at'],
        ] as $value) {
            $this->assertSame('2026-11-20T23:30:00+09:00', $value);
        }
    }

    public function test_stale_queued_research_job_is_failed_in_api_19_and_api_11(): void
    {
        $company = $this->makeCompany($this->userId);
        $job = $this->makeJob($company->id, '未処理', now()->subMinutes(6));

        $detail = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));
        $jobShow = $this->getJson("/api/v1/jobs/{$job->id}", $this->headers($this->userId));

        $detail->assertJsonPath('data.research_status', 'failed');
        $jobShow->assertJsonPath('data.status', 'failed');
        // 読むだけで、行は書き換えない
        $this->assertDatabaseHas('ai_jobs', ['id' => $job->id, 'status' => '未処理', 'finished_at' => null]);
    }

    public function test_research_status_uses_latest_research_job_only(): void
    {
        $company = $this->makeCompany($this->userId);
        $this->makeJob($company->id, '完了', now()->subDays(1));
        $this->makeJob($company->id, '生成中', now()->subMinute());
        $this->makeJob($company->id, '失敗', now(), '最新トピックス再取得');

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $response->assertJsonPath('data.research_status', 'processing');
    }

    public function test_api_11_status_mapping_is_unchanged(): void
    {
        $company = $this->makeCompany($this->userId);
        $cases = [
            ['生成中', now()->subMinute(), 'processing'],
            ['未処理', now()->subMinute(), 'queued'],
            ['生成中', now()->subMinutes(6), 'failed'],
            ['完了', now()->subMinutes(30), 'succeeded'],
            ['失敗', now()->subMinute(), 'failed'],
        ];

        foreach ($cases as [$status, $createdAt, $expected]) {
            $job = $this->makeJob($company->id, $status, $createdAt);

            $this->getJson("/api/v1/jobs/{$job->id}", $this->headers($this->userId))
                ->assertStatus(200)
                ->assertJsonPath('data.status', $expected)
                ->assertJsonPath('data.job_type', 'research_company');
        }
    }

    public function test_entry_sheets_are_ordered_by_updated_at_desc_then_id_asc(): void
    {
        $company = $this->makeCompany($this->userId);
        $older = $this->addEntrySheet($company->id, '夏インターン', '2026-11-10 09:00:00');
        $newer = $this->addEntrySheet($company->id, '本選考', '2026-11-12 18:30:00');

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $response->assertStatus(200);
        $response->assertJsonPath('data.entry_sheets', [
            ['id' => $newer, 'title' => '本選考', 'updated_at' => '2026-11-12T18:30:00+09:00'],
            ['id' => $older, 'title' => '夏インターン', 'updated_at' => '2026-11-10T09:00:00+09:00'],
        ]);
    }

    public function test_entry_sheets_with_same_updated_at_are_ordered_by_id_asc(): void
    {
        $company = $this->makeCompany($this->userId);
        $idB = $this->addEntrySheet($company->id, 'B', '2026-11-11 09:00:00', 'bbbbbbbb-0000-4000-8000-000000000000');
        $idA = $this->addEntrySheet($company->id, 'A', '2026-11-11 09:00:00', 'aaaaaaaa-0000-4000-8000-000000000000');

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $this->assertSame([$idA, $idB], array_column($response->json('data.entry_sheets'), 'id'));
    }

    public function test_entry_sheets_is_empty_array_when_no_entry_sheets(): void
    {
        $company = $this->makeCompany($this->userId);

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $response->assertStatus(200);
        $this->assertArrayHasKey('entry_sheets', $response->json('data'));
        $response->assertJsonPath('data.entry_sheets', []);
    }

    public function test_entry_sheets_exclude_other_companies_and_other_users(): void
    {
        $company = $this->makeCompany($this->userId);
        $anotherCompany = $this->makeCompany($this->userId, [
            'company_name' => '株式会社ほか',
            'normalized_company_name' => '株式会社ほか',
        ]);
        $otherUsersCompany = $this->makeCompany($this->otherUserId);
        $own = $this->addEntrySheet($company->id, '本選考', '2026-11-10 09:00:00');
        $this->addEntrySheet($anotherCompany->id, '別の企業のES', '2026-11-11 09:00:00');
        $this->addEntrySheet($otherUsersCompany->id, 'ほかの人のES', '2026-11-12 09:00:00');

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $this->assertSame([$own], array_column($response->json('data.entry_sheets'), 'id'));
    }

    public function test_entry_sheets_do_not_include_questions_or_answers(): void
    {
        $company = $this->makeCompany($this->userId);
        $esId = $this->addEntrySheet($company->id, '本選考', '2026-11-10 09:00:00');
        DB::table('es_answers')->insert([
            'id' => (string) Str::uuid(),
            'entry_sheet_id' => $esId,
            'item_type' => '志望動機',
            'question' => '設問の本文です',
            'answer' => '回答の本文です',
            'max_chars' => 400,
            'sort_order' => 1,
            'ai_draft' => 'AIの初案です',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/companies/{$company->id}", $this->headers($this->userId));

        $entrySheets = $response->json('data.entry_sheets');
        $this->assertCount(1, $entrySheets);
        $this->assertSame(['id', 'title', 'updated_at'], array_keys($entrySheets[0]));
        $body = $response->getContent();
        $this->assertStringNotContainsString('設問の本文です', $body);
        $this->assertStringNotContainsString('回答の本文です', $body);
        $this->assertStringNotContainsString('AIの初案です', $body);
    }
}
