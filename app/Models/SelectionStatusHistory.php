<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 選考ステータスの変更履歴（DB定義書 v7）。API-20 の変更のたびに1行残す。
 */
class SelectionStatusHistory extends Model
{
    use FormatsDatesForApi, HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'from_status',
        'to_status',
        'changed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
