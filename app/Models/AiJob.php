<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AiJob extends Model
{
    use FormatsDatesForApi, HasUuids;

    /** @var array<string, string> DB の status => API の status（API定義書 v18 6.7） */
    public const API_STATUSES = [
        '未処理' => 'queued',
        '生成中' => 'processing',
        '完了' => 'succeeded',
        '失敗' => 'failed',
    ];

    /**
     * ai_jobs は updated_at を持たない。created_at は DB の既定値で入る。
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_type',
        'company_id',
        'entry_sheet_id',
        'status',
        'progress_step',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress_step' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * API で返す状態（API-11・API-19 で共通。詳細設計書 v5 第5章）。
     * 「未処理」「生成中」のまま作成から5分を過ぎたものは failed として返す。
     * 読むだけで、行の状態は書き換えない。
     */
    public function apiStatus(): string
    {
        $status = self::API_STATUSES[$this->status] ?? $this->status;

        if (in_array($status, ['queued', 'processing'], true)
            && $this->created_at !== null
            && Carbon::parse($this->created_at)->lt(now()->subMinutes(5))) {
            return 'failed';
        }

        return $status;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entrySheet(): BelongsTo
    {
        return $this->belongsTo(EntrySheet::class);
    }
}
