<?php

namespace App\Support\Supabase;

use Illuminate\Support\Facades\Http;
use Throwable;

class AdminClient
{
    /**
     * Supabase の管理用 API で利用者を物理削除する（退会・公開終了処理で使う）。
     * public 側の行は ON DELETE CASCADE で一緒に消える。
     * シークレットキーはサーバー側でのみ使う。
     */
    public function deleteUser(string $userId): bool
    {
        $base = rtrim((string) config('supabase.url'), '/');
        $secret = config('supabase.secret_key');
        if ($base === '' || ! is_string($secret) || $secret === '') {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $secret,
                'Authorization' => 'Bearer '.$secret,
            ])->timeout(10)->delete($base.'/auth/v1/admin/users/'.$userId);

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
