<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * 選考ステータス履歴（DB定義書 v7 selection_status_histories）。
 * created_at・updated_at の列は無い。変更日時は changed_at（DB の既定値で入る）。
 */
class SelectionStatusHistory extends Model
{
    use FormatsDatesForApi, HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }
}
