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
     * DB定義書 v7 の companies・company_login_infos・selection_status_histories。
     * 企業研究の状態は ai_jobs の最新行で判定するため、companies に研究を作ったAIの列は無い。
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
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
            $table->timestampTz('research_generated_at')->nullable();
            $table->timestampTz('topics_fetched_at')->nullable();
            $table->text('search_suggestions_html')->nullable();
            $table->jsonb('source_links')->nullable();
            $table->text('topics_search_suggestions_html')->nullable();
            $table->jsonb('topics_source_links')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['user_id', 'normalized_company_name']);
        });

        DB::statement("alter table public.companies add constraint companies_company_name_check check (char_length(company_name) <= 100)");
        DB::statement("alter table public.companies add constraint companies_industry_check check (industry in ('メーカー（機械・電機・自動車）','メーカー（素材・化学）','メーカー（食品・日用品・医薬品）','商社','小売・流通','金融（銀行・証券・保険）','IT・ソフトウェア・通信','広告・出版・マスコミ','コンサルティング','建設・不動産','運輸・物流','電力・ガス・エネルギー','人材・教育','医療・福祉','ホテル・旅行・外食・レジャー','官公庁・団体','その他'))");
        DB::statement("alter table public.companies add constraint companies_job_info_text_check check (char_length(job_info_text) <= 5000)");
        DB::statement("alter table public.companies add constraint companies_selection_status_check check (selection_status in ('検討中','エントリー済み','書類選考中','一次面接','二次面接','最終面接','内定','お見送り','辞退'))");

        Schema::create('company_login_infos', function (Blueprint $table) {
            $table->foreignUuid('company_id')->primary()->constrained('companies')->cascadeOnDelete();
            $table->text('login_url')->nullable();
            $table->text('login_id_encrypted')->nullable();
            $table->text('password_encrypted')->nullable();
        });

        Schema::create('selection_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->text('from_status')->nullable();
            $table->text('to_status');
            $table->timestampTz('changed_at')->useCurrent();

            $table->index(['company_id', 'changed_at'], 'selection_status_histories_company_changed_idx');
        });

        DB::statement("alter table public.selection_status_histories add constraint selection_status_histories_from_status_check check (from_status in ('検討中','エントリー済み','書類選考中','一次面接','二次面接','最終面接','内定','お見送り','辞退'))");
        DB::statement("alter table public.selection_status_histories add constraint selection_status_histories_to_status_check check (to_status in ('検討中','エントリー済み','書類選考中','一次面接','二次面接','最終面接','内定','お見送り','辞退'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selection_status_histories');
        Schema::dropIfExists('company_login_infos');
        Schema::dropIfExists('companies');
    }
};
