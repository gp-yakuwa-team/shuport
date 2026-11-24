<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * 利用者（public.users。DB定義書 v7）。
 * 認証そのもの（登録・ログイン・パスワード）は Supabase Auth が持ち、
 * このモデルはアプリ側の利用者情報を表す。メールアドレスやパスワードは
 * auth.users 側にあり、このテーブルには持たない。
 * 主キーは auth.users の ID と同じ UUID（トリガーで作成される）。
 */
#[Fillable(['line_user_id', 'terms_accepted_at', 'terms_version', 'is_demo'])]
class User extends Authenticatable
{
    use FormatsDatesForApi, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }
}
