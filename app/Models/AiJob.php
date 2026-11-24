<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiJob extends Model
{
    use FormatsDatesForApi, HasUuids;

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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entrySheet(): BelongsTo
    {
        return $this->belongsTo(EntrySheet::class);
    }
}
