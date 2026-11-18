<?php

namespace App\Jobs;

class GenerateEsDraftJob extends AiJobBase
{
    /**
     * タイムアウトは15秒（DB定義書 v7。テストで見直す）。
     */
    public int $timeout = 15;

    public function __construct(
        public string $entrySheetId,
        ?string $aiJobId = null,
    ) {
        parent::__construct($aiJobId);
    }

    public function handle(): void
    {
        $this->markStarted();

        // TODO(D1): PII をマスキングしてメインAI（Gemini）で初案を生成し、
        // es_answers.ai_draft へ保存する。回答欄（answer）は上書きしない。
        // メインAIが使えない場合はサブAI（Workers AI）へ切り替える。

        $this->markCompleted();
    }
}
