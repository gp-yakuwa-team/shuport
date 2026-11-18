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
     * DB定義書 v7 の user_profiles。
     * jsonb の中身（学歴・資格・リンク・自由記述の形と件数）は画面とサーバーで確認する。
     * has_qualifications が空なら未選択で、必須の判定は画面側で行う。
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->jsonb('academic_background')->nullable();
            $table->boolean('has_qualifications')->nullable();
            $table->jsonb('qualifications')->nullable();
            $table->text('self_pr')->nullable();
            $table->text('gakuchika')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('extracurricular')->nullable();
            $table->text('club_circle')->nullable();
            $table->text('hobbies_skills')->nullable();
            $table->jsonb('links')->nullable();
            $table->jsonb('free_entries')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        foreach (['self_pr', 'gakuchika', 'strengths', 'weaknesses', 'extracurricular', 'club_circle'] as $column) {
            DB::statement("alter table public.user_profiles add constraint user_profiles_{$column}_check check (char_length({$column}) <= 1000)");
        }
        DB::statement('alter table public.user_profiles add constraint user_profiles_hobbies_skills_check check (char_length(hobbies_skills) <= 200)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
