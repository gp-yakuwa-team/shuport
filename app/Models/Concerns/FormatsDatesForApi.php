<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * API の応答で使う日時の形（2026年11月22日 決定20）。
 * 日時は +09:00 付きの ISO 8601、日付だけの項目は YYYY-MM-DD で返す。
 * 日付だけの項目は、そのモデルを作るときに casts を 'date:Y-m-d' にする
 * （何も指定しない date だと、日時の形〈例：2026-11-20T00:00:00+09:00〉になる）。
 * モデルに日時を入れるときも、オフセット付きの日時は日本時間に変えてから保存の形にする。
 */
trait FormatsDatesForApi
{
    /**
     * 既定の処理はオフセットを捨てて 'Y-m-d H:i:s' にするため、UTC などの日時が9時間ずれる。
     * オフセットのない日時は、今までどおり日本時間として扱う。
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }

        // asDateTime() と CarbonImmutable::instance() は写しを作る。受け取った値は書き換えない。
        return CarbonImmutable::instance($this->asDateTime($value))
            ->setTimezone('Asia/Tokyo')
            ->format($this->getDateFormat());
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        // immutable の写しを日本時間に変える。元の日時の値は書き換えない。
        return CarbonImmutable::instance($date)
            ->setTimezone('Asia/Tokyo')
            ->format('Y-m-d\TH:i:sP');
    }
}
