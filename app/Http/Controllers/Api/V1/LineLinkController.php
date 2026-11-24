<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LineOneTimeCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineLinkController extends Controller
{
    /**
     * API-36 LINE連携コード発行: 6桁の連携コードを発行する（10分・1回だけ有効）。
     * 平文は保存せず、HMAC-SHA256 のハッシュだけを line_one_time_codes に置く（決定18）。
     * 再発行すると前のコードは上書きされて無効になる。
     * 体験用アカウントは 403（ルートの not.demo）。連携の確定は LINE Webhook（API-12）側で行う。
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        $pepper = (string) (config('line.link_code_pepper') ?: config('app.key'));

        LineOneTimeCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => hash_hmac('sha256', $code, $pepper),
                'expires_at' => $expiresAt,
            ]
        );

        return response()->json(['data' => [
            'code' => $code,
            'expires_at' => $expiresAt->toIso8601String(),
        ]], 201);
    }

    /**
     * API-37 LINE連携解除: 連携を解除する。成功時は 204 No Content。
     * 発行済みの連携コードが残っていれば一緒に消す。
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['line_user_id' => null]);
        LineOneTimeCode::query()->where('user_id', $user->id)->delete();

        return response()->json(null, 204);
    }
}
