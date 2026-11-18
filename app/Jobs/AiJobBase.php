<?php

namespace App\Jobs;

use App\Models\AiJob;
use App\Support\ErrorRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * ai_jobs に状態を持つ AI ジョブの共通基盤。
 * 状態は「未処理 → 生成中 → 完了／失敗」で、企業の研究状態や ES の生成状態は
 * ai_jobs の最新行で判定する（DB定義書 v7）。
 * 「未処理」「生成中」のまま5分を経過した行は「失敗」として扱う（読み取り側の判定）。
 */
abstract class AiJobBase implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $aiJobId = null,
    ) {}

    /**
     * 投入時に ai_jobs の行を作る。呼び出し側（API の受け口）は、
     * 返ってきた行の id をジョブの aiJobId に渡して投入する。
     */
    public static function createRecord(string $jobType, ?string $companyId = null, ?string $entrySheetId = null): AiJob
    {
        return AiJob::create([
            'job_type' => $jobType,
            'company_id' => $companyId,
            'entry_sheet_id' => $entrySheetId,
            'status' => '未処理',
        ]);
    }

    protected function markStarted(): void
    {
        $this->record()?->update([
            'status' => '生成中',
            'started_at' => now(),
        ]);
    }

    protected function markProgress(int $step): void
    {
        $this->record()?->update([
            'progress_step' => $step,
        ]);
    }

    protected function markCompleted(): void
    {
        $this->record()?->update([
            'status' => '完了',
            'finished_at' => now(),
        ]);
    }

    protected function markFailed(): void
    {
        $this->record()?->update([
            'status' => '失敗',
            'finished_at' => now(),
        ]);
    }

    /**
     * 最終的に失敗したときは ai_jobs を「失敗」にし、error_logs に記録する。
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed();

        if ($exception !== null) {
            ErrorRecorder::record($exception, 'バックグラウンド処理');
        }
    }

    private function record(): ?AiJob
    {
        return $this->aiJobId !== null ? AiJob::find($this->aiJobId) : null;
    }
}
