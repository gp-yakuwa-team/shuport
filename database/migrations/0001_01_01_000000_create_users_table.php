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
     * DB定義書 v7 の users テーブル。
     * id は Supabase Auth の auth.users.id と同じ値を使う（パスワード等は Auth が管理）。
     * password_reset_tokens と sessions は作らない（パスワード再設定は Supabase Auth、
     * セッションは cookie ドライバを使う）。
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('line_user_id')->nullable()->unique();
            $table->timestampTz('terms_accepted_at');
            $table->text('terms_version');
            $table->boolean('is_demo')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement('alter table public.users add constraint users_id_fk foreign key (id) references auth.users (id) on delete cascade');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
