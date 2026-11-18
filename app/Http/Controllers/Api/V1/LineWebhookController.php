<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineWebhookController extends Controller
{
    /**
     * API-12 LINE Webhook 受信。
     * 署名（X-Line-Signature）を検証し、成功したら直ちに 200 を返して、
     * 解析などの処理は ParseLineMessageJob へ委譲する（D2 で実装）。
     * 検証に失敗した場合は 401 を返す。受信した生の Payload は保存しない。
     */
    public function __invoke(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }
}
