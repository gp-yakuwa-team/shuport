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
     * DB定義書 v7 の line_one_time_codes・pending_line_events。
     * 連携コードの誤入力回数はテーブルを作らず、Laravel のレート制限で数える。
     */
    public function up(): void
    {
        Schema::create('line_one_time_codes', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->text('code_hash')->unique();
            $table->timestampTz('expires_at');

            $table->index('expires_at', 'line_one_time_codes_expires_idx');
        });

        Schema::create('pending_line_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('company_name')->nullable();
            $table->text('event_type');
            $table->timestampTz('start_at');
            $table->timestampTz('end_at')->nullable();
            $table->text('meeting_url')->nullable();
            $table->text('memo')->nullable();
            $table->timestampTz('expires_at');

            $table->index('user_id', 'pending_line_events_user_idx');
            $table->index('expires_at', 'pending_line_events_expires_idx');
        });

        DB::statement('alter table public.pending_line_events add constraint pending_line_events_company_name_check check (char_length(company_name) <= 100)');
        DB::statement("alter table public.pending_line_events add constraint pending_line_events_event_type_check check (event_type in ('面接','ES締切','説明会','その他'))");
        DB::statement('alter table public.pending_line_events add constraint pending_line_events_end_at_check check (end_at is null or end_at > start_at)');
        DB::statement('alter table public.pending_line_events add constraint pending_line_events_memo_check check (char_length(memo) <= 1000)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_line_events');
        Schema::dropIfExists('line_one_time_codes');
    }
};
