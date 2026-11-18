<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DB定義書 v7: auth.users へのトリガー2件。
     * - 新規登録時に public.users の行を作る。規約の版（raw_user_meta_data の terms_version）を写し、
     *   送られなかったときは登録そのものを失敗させる。同意日時はトリガーが入れる。
     * - 体験用アカウント（is_demo = true）のパスワード・メールアドレス変更を拒否する。
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  if (new.raw_user_meta_data ->> 'terms_version') is null then
    raise exception 'terms_version is required';
  end if;
  insert into public.users (id, terms_accepted_at, terms_version)
  values (new.id, now(), new.raw_user_meta_data ->> 'terms_version');
  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

create or replace function public.prevent_demo_credential_change()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  if exists (select 1 from public.users where id = new.id and is_demo) then
    if old.encrypted_password is distinct from new.encrypted_password
       or old.email is distinct from new.email
       or old.email_change is distinct from new.email_change then
      raise exception 'demo account credentials cannot be changed';
    end if;
  end if;
  return new;
end;
$$;

drop trigger if exists on_auth_user_updated on auth.users;
create trigger on_auth_user_updated
  before update on auth.users
  for each row execute function public.prevent_demo_credential_change();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
drop trigger if exists on_auth_user_updated on auth.users;
drop trigger if exists on_auth_user_created on auth.users;
drop function if exists public.prevent_demo_credential_change();
drop function if exists public.handle_new_user();
SQL);
    }
};
