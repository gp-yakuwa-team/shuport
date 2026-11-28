<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Support\LoginInfoCipher;
use App\Support\Supabase\JwtVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * API-21 採用サイトログイン情報取得（詳細設計書 v5 付録A A.4）と、
 * 専用の鍵による暗号化・復号（3.4 セキュリティ設計 3-1・3-2）の検証。
 * SQLite に必要最小限の表を作り、JwtVerifier をモックして認証済みリクエストを再現する。
 */
class CompanyLoginInfoApiTest extends TestCase
{
    private string $userId;

    private string $otherUserId;

    private string $demoUserId;

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
            $table->timestamps();
            $table->unique(['user_id', 'normalized_company_name']);
        });
        Schema::create('company_login_infos', function (Blueprint $table) {
            $table->uuid('company_id')->primary();
            $table->text('login_url')->nullable();
            $table->text('login_id_encrypted')->nullable();
            $table->text('password_encrypted')->nullable();
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
        $this->otherUserId = (string) Str::uuid();
        $this->demoUserId = (string) Str::uuid();

        foreach ([$this->userId => false, $this->otherUserId => false, $this->demoUserId => true] as $id => $isDemo) {
            DB::table('users')->insert([
                'id' => $id,
                'is_demo' => $isDemo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        config(['shuport.recruit_site_encryption_key' => $this->newKey()]);

        $verifier = Mockery::mock(JwtVerifier::class);
        $verifier->shouldReceive('verify')->andReturnUsing(
            fn (string $token) => (object) ['sub' => $token]
        );
        $this->app->instance(JwtVerifier::class, $verifier);
    }

    private function newKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc'));
    }

    private function headers(string $userId): array
    {
        return ['Authorization' => 'Bearer '.$userId];
    }

    private function makeCompany(string $userId): Company
    {
        return Company::create([
            'user_id' => $userId,
            'company_name' => '株式会社テスト',
            'normalized_company_name' => '株式会社テスト',
            'industry' => 'IT・ソフトウェア・通信',
            'selection_status' => '検討中',
        ]);
    }

    private function saveLoginInfo(string $companyId, ?string $url, ?string $loginId, ?string $password = null): void
    {
        $cipher = new LoginInfoCipher;

        DB::table('company_login_infos')->insert([
            'company_id' => $companyId,
            'login_url' => $url,
            'login_id_encrypted' => $loginId === null ? null : $cipher->encrypt($loginId),
            'password_encrypted' => $password === null ? null : $cipher->encrypt($password),
        ]);
    }

    private function url(string $companyId): string
    {
        return '/api/v1/companies/'.$companyId.'/login-info';
    }

    public function test_returns_url_and_decrypted_login_id(): void
    {
        $company = $this->makeCompany($this->userId);
        $this->saveLoginInfo($company->id, 'https://example.co.jp/login', 'user@example.co.jp', 'secret-pass');

        $this->getJson($this->url($company->id), $this->headers($this->userId))
            ->assertOk()
            ->assertExactJson(['data' => [
                'login_url' => 'https://example.co.jp/login',
                'login_id' => 'user@example.co.jp',
            ]]);
    }

    public function test_returns_nulls_when_not_registered(): void
    {
        $company = $this->makeCompany($this->userId);

        $this->getJson($this->url($company->id), $this->headers($this->userId))
            ->assertOk()
            ->assertExactJson(['data' => ['login_url' => null, 'login_id' => null]]);
    }

    public function test_response_has_no_password(): void
    {
        $company = $this->makeCompany($this->userId);
        $this->saveLoginInfo($company->id, 'https://example.co.jp/login', 'user@example.co.jp', 'secret-pass');

        $response = $this->getJson($this->url($company->id), $this->headers($this->userId))->assertOk();

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertArrayNotHasKey('password_encrypted', $response->json('data'));
        $this->assertStringNotContainsString('secret-pass', $response->getContent());
    }

    public function test_other_users_company_returns_404(): void
    {
        $company = $this->makeCompany($this->otherUserId);
        $this->saveLoginInfo($company->id, 'https://example.co.jp/login', 'other@example.co.jp');

        $response = $this->getJson($this->url($company->id), $this->headers($this->userId))
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertStringNotContainsString('other@example.co.jp', $response->getContent());
    }

    public function test_missing_company_returns_404(): void
    {
        $this->getJson($this->url((string) Str::uuid()), $this->headers($this->userId))
            ->assertNotFound();
    }

    public function test_requires_token(): void
    {
        $company = $this->makeCompany($this->userId);

        $this->getJson($this->url($company->id))->assertUnauthorized();
    }

    public function test_demo_account_can_read(): void
    {
        $company = $this->makeCompany($this->demoUserId);
        $this->saveLoginInfo($company->id, 'https://example.co.jp/login', 'demo@example.co.jp');

        $this->getJson($this->url($company->id), $this->headers($this->demoUserId))
            ->assertOk()
            ->assertJsonPath('data.login_id', 'demo@example.co.jp');
    }

    public function test_missing_key_returns_500_without_plaintext(): void
    {
        $company = $this->makeCompany($this->userId);
        $this->saveLoginInfo($company->id, 'https://example.co.jp/login', 'user@example.co.jp');
        $encrypted = (string) DB::table('company_login_infos')->value('login_id_encrypted');

        config(['shuport.recruit_site_encryption_key' => null]);

        $response = $this->getJson($this->url($company->id), $this->headers($this->userId))
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertStringNotContainsString('user@example.co.jp', $response->getContent());
        $this->assertStringNotContainsString($encrypted, $response->getContent());
        $this->assertDatabaseHas('error_logs', ['error_type' => '例外', 'message' => 'login_info_key_missing']);
    }

    public function test_wrong_key_returns_500_without_plaintext(): void
    {
        $company = $this->makeCompany($this->userId);
        $this->saveLoginInfo($company->id, 'https://example.co.jp/login', 'user@example.co.jp');

        config(['shuport.recruit_site_encryption_key' => $this->newKey()]);

        $response = $this->getJson($this->url($company->id), $this->headers($this->userId))
            ->assertStatus(500);

        $this->assertStringNotContainsString('user@example.co.jp', $response->getContent());
        $this->assertDatabaseHas('error_logs', ['message' => 'login_info_decrypt_failed']);
    }

    public function test_broken_ciphertext_returns_500(): void
    {
        $company = $this->makeCompany($this->userId);
        DB::table('company_login_infos')->insert([
            'company_id' => $company->id,
            'login_url' => 'https://example.co.jp/login',
            'login_id_encrypted' => 'broken-ciphertext',
        ]);

        $response = $this->getJson($this->url($company->id), $this->headers($this->userId))
            ->assertStatus(500);

        $this->assertStringNotContainsString('broken-ciphertext', $response->getContent());
        $this->assertDatabaseHas('error_logs', ['message' => 'login_info_decrypt_failed']);
    }

    public function test_decrypts_after_app_key_changes(): void
    {
        $company = $this->makeCompany($this->userId);
        $this->saveLoginInfo($company->id, 'https://example.co.jp/login', 'user@example.co.jp');

        config(['app.key' => $this->newKey()]);
        $this->app->forgetInstance('encrypter');

        $this->getJson($this->url($company->id), $this->headers($this->userId))
            ->assertOk()
            ->assertJsonPath('data.login_id', 'user@example.co.jp');
    }

    public function test_same_value_encrypts_differently(): void
    {
        $cipher = new LoginInfoCipher;

        $first = $cipher->encrypt('user@example.co.jp');
        $second = $cipher->encrypt('user@example.co.jp');

        $this->assertNotSame($first, $second);
        $this->assertNotSame('user@example.co.jp', $first);
        $this->assertSame('user@example.co.jp', $cipher->decrypt($first));
        $this->assertSame('user@example.co.jp', $cipher->decrypt($second));
    }
}
