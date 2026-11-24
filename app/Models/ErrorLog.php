<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesAsJst;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use HasUuids, SerializesDatesAsJst;

    /**
     * error_logs は作成日時を持たず、発生日時は occurred_at で表す。
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'occurred_at',
        'error_type',
        'location',
        'message',
        'stack_trace',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
