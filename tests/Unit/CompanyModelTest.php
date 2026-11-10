<?php

namespace Tests\Unit;

use App\Models\Company;
use PHPUnit\Framework\TestCase;

class CompanyModelTest extends TestCase
{
    /**
     * Company モデルが companies テーブルに対応することを確認する。
     */
    public function test_company_model_uses_companies_table(): void
    {
        $this->assertSame('companies', (new Company)->getTable());
    }
}
