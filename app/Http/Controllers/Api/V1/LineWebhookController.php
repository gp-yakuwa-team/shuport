<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ParseLineMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineWebhookController extends Controller
{
    /**
     * API-12 LINE Webhook 受信。
     * 署名の検証は VerifyLineSignature（line.signature）が先に行う。
     * 検証が済んだら直ちに 200 を返し、メッセージの解析は
     * ParseLineMessageJob へ委譲する。受信した生の Payload は保存しない。
     */
    public function __invoke(Request $request): JsonResponse
    {
        $events = $request->input('events', []);

        if (is_array($events)) {
            foreach ($events as $event) {
                if (is_array($event) && ($event['type'] ?? null) === 'message') {
                    ParseLineMessageJob::dispatch($event);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
