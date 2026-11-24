<?php

namespace Tests\Unit;

use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * 決定20: API の日時は +09:00 付き ISO 8601 で返す。
 */
class DateSerializationTest extends TestCase
{
    public function test_datetime_is_serialized_in_jst_with_offset(): void
    {
        $user = new User;
        $user->terms_accepted_at = CarbonImmutable::create(2026, 11, 24, 9, 0, 0, 'Asia/Tokyo');

        $array = $user->toArray();

        $this->assertSame('2026-11-24T09:00:00+09:00', $array['terms_accepted_at']);
    }
}
