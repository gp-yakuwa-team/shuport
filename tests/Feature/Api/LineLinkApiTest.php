<?php

namespace Tests\Feature\Api;

use App\Models\LineOneTimeCode;
use App\Support\Supabase\JwtVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * LINE連携コードの発行（API-36）と連携解除（API-37）の検証。
 */
class LineLinkApiTest extends TestCase
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
        Schema::create('line_one_time_codes', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('code_hash')->unique();
            $table->timestamp('expires_at');
        });

        $this->userId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $this->userId,
            'line_user_id' => 'U1234567890',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $verifier = Mockery::mock(JwtVerifier::class);
        $verifier->shouldReceive('verify')->andReturnUsing(
            fn (string $token) => (object) ['sub' => $token]
        );
        $this->app->instance(JwtVerifier::class, $verifier);

        config(['line.link_code_pepper' => 'test-pepper']);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->userId];
    }

    public function test_store_issues_six_digit_code_and_stores_only_hash(): void
    {
        $response = $this->postJson('/api/v1/line/link-code', [], $this->headers());

        $response->assertStatus(201);
        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotNull($response->json('data.expires_at'));

        $row = LineOneTimeCode::find($this->userId);
        $this->assertNotNull($row);
        $this->assertSame(hash_hmac('sha256', $code, 'test-pepper'), $row->code_hash);
        $this->assertTrue($row->expires_at->between(now()->addMinutes(9), now()->addMinutes(11)));
    }

    public function test_store_reissue_overwrites_previous_code(): void
    {
        $this->postJson('/api/v1/line/link-code', [], $this->headers())->assertStatus(201);
        $this->postJson('/api/v1/line/link-code', [], $this->headers())->assertStatus(201);

        $this->assertSame(1, LineOneTimeCode::count());
    }

    public function test_destroy_unlinks_line_account(): void
    {
        $this->postJson('/api/v1/line/link-code', [], $this->headers())->assertStatus(201);

        $response = $this->deleteJson('/api/v1/line/link', [], $this->headers());

        $response->assertStatus(204);
        $this->assertDatabaseHas('users', ['id' => $this->userId, 'line_user_id' => null]);
        $this->assertSame(0, LineOneTimeCode::count());
    }
}
