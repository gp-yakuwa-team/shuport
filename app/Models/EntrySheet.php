<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ES（DB定義書 v7 entry_sheets）。1社に複数作成できる。
 */
class EntrySheet extends Model
{
    use FormatsDatesForApi;
    use HasUuids;

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EsAnswer::class)->orderBy('sort_order');
    }
}
