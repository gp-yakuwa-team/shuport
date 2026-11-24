<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * モデルの JSON の日時を、+09:00 を付けた ISO 8601（秒の小数なし）にする。
 * 例：2026-11-20T20:10:00+09:00（API定義書 v18 6.1。決定20）。
 * Eloquent の既定は UTC の Z 付きのため、すべてのモデルでこの trait を使う。
 * モデルに日時を入れるときも、オフセット付きの日時は日本時間に変えてから保存の形にする。
 */
trait SerializesDatesAsJst
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
