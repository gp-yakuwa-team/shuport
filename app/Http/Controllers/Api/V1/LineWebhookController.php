<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ErrorRecorder;
use App\Support\Line\LineWebhookProcessor;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineWebhookController extends Controller
{
    /**
     * API-12 LINE Webhook 受信。
     * 署名の検証は VerifyLineSignature（line.signature）が先に行う。
     * 検証が済んだら直ちに 200 を返せるよう、イベントごとの処理は
     * LineWebhookProcessor が同期で行い、自由文の解析だけを ParseLineMessageJob へ委譲する。
     * 受信した生の Payload は保存しない。DB の障害のときも 200 を返し、返信しない（決定30）。
     */
    public function __invoke(Request $request, LineWebhookProcessor $processor): JsonResponse
    {
        $events = $request->input('events', []);

        if (is_array($events)) {
            foreach ($events as $event) {
                if (! is_array($event)) {
                    continue;
                }

                try {
                    $processor->handle($event);
                } catch (QueryException $e) {
                    ErrorRecorder::record($e, 'データベース');
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
