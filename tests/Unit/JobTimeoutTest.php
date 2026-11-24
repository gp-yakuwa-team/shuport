<?php

namespace Tests\Unit;

use App\Jobs\GenerateEsDraftJob;
use App\Jobs\ParseLineMessageJob;
use App\Jobs\RefreshCompanyTopicsJob;
use App\Jobs\ResearchCompanyJob;
use PHPUnit\Framework\TestCase;

/**
 * ジョブの $timeout は詳細設計 4.10 の値に合わせる（AGENTS.md の決定事項）。
 * プロバイダへの時間配分（決定19）とは別の、ジョブ全体の上限。
 */
class JobTimeoutTest extends TestCase
{
    public function test_timeouts_match_design_4_10(): void
    {
        $this->assertSame(40, (new ResearchCompanyJob('00000000-0000-0000-0000-000000000000'))->timeout);
        $this->assertSame(40, (new RefreshCompanyTopicsJob('00000000-0000-0000-0000-000000000000'))->timeout);
        $this->assertSame(25, (new GenerateEsDraftJob('00000000-0000-0000-0000-000000000000'))->timeout);
        $this->assertSame(30, (new ParseLineMessageJob([]))->timeout);
    }
}
