<?php

namespace Tests\Unit;

use App\Models\AiJob;
use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 日時のタイムゾーンと JSON の形（API定義書 v18 6.1。決定20）。
 * モデルは new で作り保存しないため、DB には触れない。
 */
class JstDateTimeSerializationTest extends TestCase
{
    public function test_app_and_pgsql_timezone_are_asia_tokyo(): void
    {
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('Asia/Tokyo', config('database.connections.pgsql.timezone'));
    }

    /**
     * app/Models のすべてのモデルが trait を使う。新しいモデルで付け忘れると落ちる。
     */
    public function test_every_model_uses_formats_dates_for_api(): void
    {
        $models = [];

        foreach (File::allFiles(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $models[] = $class;
        }

        $this->assertNotEmpty($models, 'app/Models にモデルが見つからない');

        foreach ($models as $class) {
            $this->assertContains(
                FormatsDatesForApi::class,
                class_uses_recursive($class),
                "{$class} が FormatsDatesForApi を使っていない",
            );
        }
    }

    /**
     * オフセットのない日時は日本時間として扱い、+09:00 を付けて返す。
     */
    public function test_datetime_without_offset_is_serialized_with_plus_0900(): void
    {
        $job = new AiJob(['started_at' => '2026-11-20 20:10:00']);

        $this->assertSame('2026-11-20T20:10:00+09:00', $job->toArray()['started_at']);
    }

    public function test_fractional_seconds_are_not_included(): void
    {
        $job = new AiJob(['started_at' => '2026-11-20 20:10:00.123456']);

        $this->assertSame('2026-11-20T20:10:00+09:00', $job->toArray()['started_at']);
    }

    /**
     * モデルに入れたオフセット付きの日時は、日本時間に変えてから持つ（9時間ずれない）。
     */
    public function test_assigned_datetime_with_offset_is_converted_to_jst(): void
    {
        $job = new AiJob(['started_at' => '2026-11-20T11:10:00Z']);

        $this->assertSame('2026-11-20 20:10:00', $job->getAttributes()['started_at']);
        $this->assertSame('2026-11-20T20:10:00+09:00', $job->toArray()['started_at']);
    }

    /**
     * モデルに入れたオフセットのない日時は、日本時間のまま持つ。
     */
    public function test_assigned_datetime_without_offset_stays_jst(): void
    {
        $job = new AiJob(['started_at' => '2026-11-20 20:10:00']);

        $this->assertSame('2026-11-20 20:10:00', $job->getAttributes()['started_at']);
        $this->assertSame('2026-11-20T20:10:00+09:00', $job->toArray()['started_at']);
    }

    /**
     * UTC の Carbon をモデルに入れても、渡した Carbon の値は書き換えない。
     */
    public function test_assigning_utc_carbon_does_not_mutate_original(): void
    {
        $utc = Carbon::parse('2026-11-20T11:10:00Z');

        $job = new AiJob(['started_at' => $utc]);

        $this->assertSame('2026-11-20T20:10:00+09:00', $job->toArray()['started_at']);
        $this->assertSame('2026-11-20 11:10:00+00:00', $utc->format('Y-m-d H:i:sP'));
    }

    /**
     * UTC の日時も日本時間に変えて返し、渡した日時の値は書き換えない。
     */
    public function test_utc_datetime_is_converted_without_mutating_original(): void
    {
        $utc = Carbon::parse('2026-11-20T11:10:00Z');

        $method = new ReflectionMethod(AiJob::class, 'serializeDate');
        $result = $method->invoke(new AiJob, $utc);

        $this->assertSame('2026-11-20T20:10:00+09:00', $result);
        $this->assertSame('2026-11-20 11:10:00+00:00', $utc->format('Y-m-d H:i:sP'));
    }
}
