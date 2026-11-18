<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DB定義書 v7 の error_logs・app_states・deleted_accounts。
     * app_states は LINE 定時通知の停止状態だけを持つ（送信数と上限は LINE API で取得する）。
     * deleted_accounts は退会済みの確認用で、利用者への外部キーは持たない。
     */
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->timestampTz('occurred_at')->useCurrent();
            $table->text('error_type');
            $table->text('location')->nullable();
            $table->text('message');
            $table->text('stack_trace')->nullable();

            $table->index('occurred_at', 'error_logs_occurred_idx');
        });

        DB::statement("alter table public.error_logs add constraint error_logs_error_type_check check (error_type in ('例外','外部サービス','バックグラウンド処理','定期処理','データベース'))");

        Schema::create('app_states', function (Blueprint $table) {
            $table->text('key')->primary();
            $table->jsonb('value');
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('deleted_accounts', function (Blueprint $table) {
            $table->text('user_id_hash')->primary();
            $table->timestampTz('deleted_at')->useCurrent();

            $table->index('deleted_at', 'deleted_accounts_deleted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_accounts');
        Schema::dropIfExists('app_states');
        Schema::dropIfExists('error_logs');
    }
};
