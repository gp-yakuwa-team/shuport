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
     * DB定義書 v7 の entry_sheets・es_answers。
     * PDF 出力と最大文字数の判定の対象は answer のみ。
     * max_chars の上限 2,000 はサーバー側の入力チェックで確認し、DB 制約は足さない（決定事項）。
     * (entry_sheet_id, sort_order) の一意制約は並べ替えのため遅延制約にする。
     */
    public function up(): void
    {
        Schema::create('entry_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->text('title');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index('company_id', 'entry_sheets_company_id_idx');
        });

        DB::statement('alter table public.entry_sheets add constraint entry_sheets_title_check check (char_length(title) <= 50)');

        Schema::create('es_answers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('entry_sheet_id')->constrained('entry_sheets')->cascadeOnDelete();
            $table->text('item_type');
            $table->text('question');
            $table->text('answer')->nullable();
            $table->integer('max_chars');
            $table->integer('sort_order');
            $table->text('ai_draft')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement("alter table public.es_answers add constraint es_answers_item_type_check check (item_type in ('自己PR','ガクチカ','長所・短所','志望動機','資格','学業以外で力を入れたこと','クラブ・サークル','趣味・特技','自由'))");
        DB::statement('alter table public.es_answers add constraint es_answers_question_check check (char_length(question) <= 500)');
        DB::statement('alter table public.es_answers add constraint es_answers_answer_check check (answer is null or char_length(answer) <= max_chars)');
        DB::statement('alter table public.es_answers add constraint es_answers_max_chars_check check (max_chars >= 1)');
        DB::statement('alter table public.es_answers add constraint es_answers_entry_sheet_id_sort_order_unique unique (entry_sheet_id, sort_order) deferrable initially deferred');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('es_answers');
        Schema::dropIfExists('entry_sheets');
    }
};
