<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DB定義書 v7「全体の決まり」: 置き場所と読み書きの制限、接続ロール。
     * - 全21テーブルで RLS を有効化する（ポリシーは作らない）
     * - anon / authenticated の権限を取り消す（既存分と今後作るものへの既定分）
     * - Laravel 専用の接続ロール shuport_app（BYPASSRLS）を作る。
     *   パスワードはこの migration には含めず、チームが別途設定する。
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
alter table public.users enable row level security;
alter table public.user_profiles enable row level security;
alter table public.companies enable row level security;
alter table public.company_login_infos enable row level security;
alter table public.selection_status_histories enable row level security;
alter table public.entry_sheets enable row level security;
alter table public.es_answers enable row level security;
alter table public.events enable row level security;
alter table public.tasks enable row level security;
alter table public.line_one_time_codes enable row level security;
alter table public.pending_line_events enable row level security;
alter table public.ai_usage_logs enable row level security;
alter table public.ai_jobs enable row level security;
alter table public.error_logs enable row level security;
alter table public.app_states enable row level security;
alter table public.deleted_accounts enable row level security;
alter table public.migrations enable row level security;
alter table public.cache enable row level security;
alter table public.cache_locks enable row level security;
alter table public.jobs enable row level security;
alter table public.failed_jobs enable row level security;

revoke all on all tables in schema public from anon, authenticated;
revoke all on all sequences in schema public from anon, authenticated;
revoke execute on all functions in schema public from anon, authenticated;
alter default privileges in schema public revoke all on tables from anon, authenticated;
alter default privileges in schema public revoke all on sequences from anon, authenticated;
alter default privileges in schema public revoke execute on functions from anon, authenticated;

create role shuport_app login bypassrls;
grant usage, create on schema public to shuport_app;
grant all on all tables in schema public to shuport_app;
grant all on all sequences in schema public to shuport_app;
grant execute on all functions in schema public to shuport_app;
alter default privileges in schema public grant all on tables to shuport_app;
alter default privileges in schema public grant all on sequences to shuport_app;
alter default privileges in schema public grant execute on functions to shuport_app;
grant usage on schema auth to shuport_app;
grant select on auth.sessions to shuport_app;
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
revoke select on auth.sessions from shuport_app;
revoke usage on schema auth from shuport_app;
alter default privileges in schema public revoke all on tables from shuport_app;
alter default privileges in schema public revoke all on sequences from shuport_app;
alter default privileges in schema public revoke execute on functions from shuport_app;
revoke all on all tables in schema public from shuport_app;
revoke all on all sequences in schema public from shuport_app;
revoke execute on all functions in schema public from shuport_app;
revoke usage, create on schema public from shuport_app;
drop role if exists shuport_app;

alter table public.users disable row level security;
alter table public.user_profiles disable row level security;
alter table public.companies disable row level security;
alter table public.company_login_infos disable row level security;
alter table public.selection_status_histories disable row level security;
alter table public.entry_sheets disable row level security;
alter table public.es_answers disable row level security;
alter table public.events disable row level security;
alter table public.tasks disable row level security;
alter table public.line_one_time_codes disable row level security;
alter table public.pending_line_events disable row level security;
alter table public.ai_usage_logs disable row level security;
alter table public.ai_jobs disable row level security;
alter table public.error_logs disable row level security;
alter table public.app_states disable row level security;
alter table public.deleted_accounts disable row level security;
alter table public.migrations disable row level security;
alter table public.cache disable row level security;
alter table public.cache_locks disable row level security;
alter table public.jobs disable row level security;
alter table public.failed_jobs disable row level security;
SQL);
    }
};
