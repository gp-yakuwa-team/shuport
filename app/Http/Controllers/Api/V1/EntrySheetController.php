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

    /**
     * API-24 ES取得: ES作成・編集画面（No.7）の内容を返す（企業名・業界、ES名、設問、回答、AIの初案）。
     * 他人の ES・存在しない ES は 404。
     */
    public function show(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-25 ES保存: ES名、設問の追加・削除・並べ替え、回答を保存する
     * （AIの初案の採用・破棄の結果を含む）。設問は最大30件、最大文字数は2,000まで（サーバー側で確認）。
     */
    public function update(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-26 ES PDF出力: ES の PDF を返す。生成ファイルはサーバーに保存せず、
     * 画面がアクセストークン付きで呼び、受け取ったファイルをブラウザで保存する（基本設計書 v16 4.6）。
     * 出力と最大文字数の判定は回答欄だけが対象（要件定義書 v18 5.3）。
     */
    public function pdf(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
