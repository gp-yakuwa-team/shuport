<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Model;

/**
 * 採用サイトログイン情報（DB定義書 v7 company_login_infos）。
 * 主キーは company_id（企業ごとに1行）。created_at・updated_at の列は無い。
 */
class CompanyLoginInfo extends Model
{
    use FormatsDatesForApi;

    protected $primaryKey = 'company_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
