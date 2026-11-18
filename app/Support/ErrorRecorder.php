<?php

namespace App\Support;

use App\Models\ErrorLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

class ErrorRecorder
{
    /**
     * 異常を error_logs に記録する。
     * 記録のルール（3.4 セキュリティ設計）: 利用者の入力文は message に含めない。
     * データベースのエラーは SQLSTATE とドライバのコードだけを記録する。
     * 記録に失敗しても本来の処理は止めない。
     */
    public static function record(Throwable $e, ?string $errorType = null): void
    {
        try {
            ErrorLog::create([
                'occurred_at' => now(),
                'error_type' => $errorType ?? self::errorTypeFor($e),
                'location' => $e::class,
                'message' => self::messageFor($e),
                'stack_trace' => Str::limit($e->getTraceAsString(), 8000, ''),
            ]);
        } catch (Throwable) {
            // 記録の失敗で本来の処理を止めない
        }
    }

    private static function errorTypeFor(Throwable $e): string
    {
        return $e instanceof QueryException ? 'データベース' : '例外';
    }

    private static function messageFor(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            $info = $e->errorInfo ?? [];
            $message = 'SQLSTATE '.($info[0] ?? 'unknown');

            if (isset($info[1])) {
                $message .= ' (driver code '.$info[1].')';
            }

            return $message;
        }

        $message = Str::limit($e->getMessage(), 500, '');

        return $message !== '' ? $message : $e::class;
    }
}
