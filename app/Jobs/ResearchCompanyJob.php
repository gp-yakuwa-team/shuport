<?php

namespace App\Jobs;

class ResearchCompanyJob extends AiJobBase
{
    /**
     * タイムアウトは30秒（決定事項。テストで見直す）。
     */
    public int $timeout = 40;

    public function __construct(
        public string $companyId,
        ?string $aiJobId = null,
    ) {
        parent::__construct($aiJobId);
    }

    public function handle(): void
    {
        $this->markStarted();

        // TODO(D1): メインAI（Gemini）で企業研究を行い、companies の研究結果の欄へ保存する。
        // 30日以内の研究結果の再利用は、投入する側（API-07・API-08・API-09・API-13）で判定する。
        // 企業研究はメインAI専用で、サブAIへは切り替えない。

        $this->markCompleted();
    }
}
