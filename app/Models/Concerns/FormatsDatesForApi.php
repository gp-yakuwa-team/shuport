<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use DateTimeZone;

/**
 * API の応答で使う日時の形（2026年11月22日 決定20）。
 * 日時は +09:00 付きの ISO 8601、日付だけの項目は YYYY-MM-DD で返す。
 * 日付だけの項目（date キャスト）は Laravel 側で YYYY-MM-DD になるため、
 * ここで扱うのは日時の項目だけ。
 */
trait FormatsDatesForApi
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d\TH:i:s+09:00');
    }
}
