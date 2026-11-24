<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesAsJst;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiJob extends Model
{
    use HasUuids, SerializesDatesAsJst;

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
}
