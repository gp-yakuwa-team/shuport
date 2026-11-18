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
     * DB定義書 v7 の events・tasks。
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->text('company_name')->nullable();
            $table->text('title');
            $table->text('event_type');
            $table->timestampTz('start_at');
            $table->timestampTz('end_at')->nullable();
            $table->text('meeting_url')->nullable();
            $table->text('memo')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['user_id', 'start_at'], 'events_user_start_idx');
            $table->index('company_id', 'events_company_id_idx');
        });

        DB::statement('alter table public.events add constraint events_company_name_check check (char_length(company_name) <= 100)');
        DB::statement('alter table public.events add constraint events_title_check check (char_length(title) <= 50)');
        DB::statement("alter table public.events add constraint events_event_type_check check (event_type in ('面接','ES締切','説明会','その他'))");
        DB::statement('alter table public.events add constraint events_end_at_check check (end_at is null or end_at > start_at)');
        DB::statement('alter table public.events add constraint events_memo_check check (char_length(memo) <= 1000)');

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->text('title');
            $table->date('due_date');
            $table->boolean('is_done')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['user_id', 'due_date'], 'tasks_user_due_idx');
            $table->index('company_id', 'tasks_company_id_idx');
        });

        DB::statement('alter table public.tasks add constraint tasks_title_check check (char_length(title) <= 50)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('events');
    }
};
