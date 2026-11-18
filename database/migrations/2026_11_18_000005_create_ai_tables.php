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
     * DB定義書 v7 の ai_usage_logs・ai_jobs。
     * 回数制限の数え方: 企業研究と最新トピックス再取得は「企業研究」枠（1日20回）、
     * それ以外は「AI生成」枠（直前1時間に20回）。体験用は全アカウント合計で1日100回。
     * ai_jobs は updated_at を持たない。
     */
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('model_name');
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->timestampTz('used_at')->useCurrent();
            $table->text('purpose');

            $table->index(['user_id', 'purpose', 'used_at'], 'ai_usage_logs_user_purpose_used_idx');
            $table->index('used_at', 'ai_usage_logs_used_idx');
        });

        DB::statement("alter table public.ai_usage_logs add constraint ai_usage_logs_purpose_check check (purpose in ('業界の候補','企業研究','最新トピックス再取得','ES生成','LINEのトーク解析'))");

        Schema::create('ai_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->text('job_type');
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('entry_sheet_id')->nullable()->constrained('entry_sheets')->cascadeOnDelete();
            $table->text('status')->default('未処理');
            $table->integer('progress_step')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'created_at'], 'ai_jobs_company_created_idx');
            $table->index(['entry_sheet_id', 'created_at'], 'ai_jobs_sheet_created_idx');
            $table->index('finished_at', 'ai_jobs_finished_idx');
        });

        DB::statement("alter table public.ai_jobs add constraint ai_jobs_job_type_check check (job_type in ('企業研究','ES生成','最新トピックス再取得'))");
        DB::statement("alter table public.ai_jobs add constraint ai_jobs_status_check check (status in ('未処理','生成中','完了','失敗'))");
        DB::statement('alter table public.ai_jobs add constraint ai_jobs_progress_step_check check (progress_step between 1 and 4)');
        DB::statement("alter table public.ai_jobs add constraint ai_jobs_target_check check ((job_type in ('企業研究','最新トピックス再取得') and company_id is not null and entry_sheet_id is null) or (job_type = 'ES生成' and entry_sheet_id is not null and company_id is null))");
        DB::statement("create index ai_jobs_pending_created_idx on public.ai_jobs (created_at) where status in ('未処理','生成中')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_jobs');
        Schema::dropIfExists('ai_usage_logs');
    }
};
