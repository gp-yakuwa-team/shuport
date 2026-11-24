<?php

namespace App\Jobs;

class RefreshCompanyTopicsJob extends AiJobBase
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

        // TODO(D1): メインAI（Gemini）で最新トピックスだけを取り直し、
        // companies のトピックスの欄へ保存する。研究全体の欄は書き換えない。
        // 企業研究と同じくメインAI専用で、サブAIへは切り替えない。

        $this->markCompleted();
    }
}
