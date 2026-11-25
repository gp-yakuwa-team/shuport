<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * LINE のトークから読み取った、承認待ちの予定（DB定義書 v7 pending_line_events）。
 * 同じ利用者の行は常に1件までで、新しい解析結果で上書きされる（4.12 K-04）。
 *
 * @property string|null $company_name
 * @property string $event_type
 * @property Carbon $start_at
 * @property Carbon|null $end_at
 * @property string|null $meeting_url
 * @property string|null $memo
 * @property Carbon $expires_at
 */
class PendingLineEvent extends Model
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
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
