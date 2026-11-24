<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 採用サイトのログイン情報（DB定義書 v7）。企業1件につき1行（company_id が主キー）。
 * ID とパスワードは APP_KEY で暗号化して保存する（3.4 セキュリティ設計 3-1）。
 */
class CompanyLoginInfo extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'company_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'login_url',
        'login_id_encrypted',
        'password_encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
