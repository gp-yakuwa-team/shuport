<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompanyRequest;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * API-07 企業登録＆AI研究開始判断。
     * 企業を登録し、原則として ResearchCompanyJob を投入して 202 を返す。
     * 企業研究の上限に達している場合とメインAIが使えない場合は、
     * 企業登録のみ完了して 201 を返す。
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }

    /**
     * API-09 企業研究の開始・再実行。
     * 他人の企業は 404 を返す。企業研究の上限に達した場合は 429 を返す。
     */
    public function research(Request $request, string $companyId): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }

    /**
     * API-10 最新トピックス再取得。
     * 他人の企業は 404 を返す。企業研究の上限に達した場合は 429 を返す。
     */
    public function refreshTopics(Request $request, string $companyId): JsonResponse
    {
        return ProblemDetails::response(501, 'この API は未実装です', $request);
    }

    /**
     * API-17 業界の候補: 企業名から業界の候補を1つ返す（No.6）。AI を同期で呼び、
     * AI生成の回数制限（1時間20回）の対象。総 deadline は15秒（仮。AI設計書 V5 3.2.4）。
     */
    public function industrySuggestion(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-18 企業一覧取得: 企業一覧（No.5）を返す（検索・絞り込み）。
     */
    public function index(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-19 企業詳細取得: 企業詳細モーダル（M1）の①③④タブの内容と、選考ステータスの履歴を返す。
     * 他人の企業・存在しない企業は 404。
     */
    public function show(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-20 選考ステータス変更: ステータスを変え、変更の履歴（selection_status_histories）を残す。
     * ステータスは確定9種のみ。他人の企業・存在しない企業は 404。
     */
    public function updateStatus(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-21 採用サイトログイン情報取得: ②タブの URL と ID を返す（パスワードは返さない。3.4 3-1）。
     */
    public function showLoginInfo(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-22 採用サイトログイン情報保存: 登録・変更する。セッションの追加確認の対象
     * （トークンの session_id が auth.sessions に存在すること。3.4 1-10）。体験用アカウントは 403。
     */
    public function updateLoginInfo(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }

    /**
     * API-23 採用サイトのパスワード表示: 伏せ字を外すときにパスワードを返す。
     * セッションの追加確認の対象（3.4 1-10）。
     */
    public function revealPassword(Request $request): JsonResponse
    {
        return ProblemDetails::response(501, '未実装です', $request);
    }
}
