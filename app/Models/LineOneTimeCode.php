<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Model;

/**
 * LINE連携のワンタイムコード（DB定義書 v7 line_one_time_codes）。
 * user_id が主キーで、平文は保存せず HMAC-SHA256 の code_hash だけを置く（決定18）。
 * 有効期限は10分・1回使い切り。照合は LINE Webhook（API-12）側で行う。
 */
class LineOneTimeCode extends Model
{
    use FormatsDatesForApi;
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
