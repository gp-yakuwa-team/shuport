<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ES の設問と回答（DB定義書 v7 es_answers）。
 * API の type（英語）と DB の item_type（日本語）は1対1に対応する（API定義書 v18 6.4）。
 */
class EsAnswer extends Model
{
    use FormatsDatesForApi;
    use HasUuids;

    /** @var array<string, string> API の type => DB の item_type */
    public const ITEM_TYPES = [
        'self_pr' => '自己PR',
        'gakuchika' => 'ガクチカ',
        'strength_weakness' => '長所・短所',
        'motivation' => '志望動機',
        'qualification' => '資格',
        'extra_activity' => '学業以外で力を入れたこと',
        'club_circle' => 'クラブ・サークル',
        'hobby_skill' => '趣味・特技',
        'free' => '自由',
    ];

    protected $guarded = [];

    public function entrySheet(): BelongsTo
    {
        return $this->belongsTo(EntrySheet::class);
    }
}
