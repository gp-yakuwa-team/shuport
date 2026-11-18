<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEntrySheetRequest;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntrySheetController extends Controller
{
    /**
     * API-08 ES新規作成＆一括AI生成開始。
     * ES を作成し、GenerateEsDraftJob を投入する。対象企業に企業研究の結果が
     * ない場合は、企業研究も開始する（企業研究の回数制限の範囲内）。
     */
    public function store(StoreEntrySheetRequest $request): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }

    /**
     * API-13 既存ESの一括AI生成開始。
     * 同じ ES の生成ジョブが「未処理」「生成中」の間に呼ばれた場合は 409 を返す。
     * 他人の ES、または存在しない場合は 404 を返す。
     */
    public function generate(Request $request, string $entrySheetId): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }
}
