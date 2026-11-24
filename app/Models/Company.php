<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 企業（DB定義書 v7）。利用者ごとに、企業名（正規化後）で一意。
 * 企業研究の結果は research_* の欄に持つ。研究の状態は ai_jobs の最新行で判定する。
 */
class Company extends Model
{
    use FormatsDatesForApi, HasUuids;

    /**
     * 業界の区分（DB定義書 v7 の17区分。companies.industry の CHECK 制約と一致させる）。
     *
     * @var list<string>
     */
    public const INDUSTRIES = [
        'メーカー（機械・電機・自動車）',
        'メーカー（素材・化学）',
        'メーカー（食品・日用品・医薬品）',
        '商社',
        '小売・流通',
        '金融（銀行・証券・保険）',
        'IT・ソフトウェア・通信',
        '広告・出版・マスコミ',
        'コンサルティング',
        '建設・不動産',
        '運輸・物流',
        '電力・ガス・エネルギー',
        '人材・教育',
        '医療・福祉',
        'ホテル・旅行・外食・レジャー',
        '官公庁・団体',
        'その他',
    ];

    /**
     * 選考ステータス（確定9種）。
     *
     * @var list<string>
     */
    public const SELECTION_STATUSES = [
        '検討中',
        'エントリー済み',
        '書類選考中',
        '一次面接',
        '二次面接',
        '最終面接',
        '内定',
        'お見送り',
        '辞退',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'company_name',
        'normalized_company_name',
        'industry',
        'job_info_text',
        'selection_status',
        'research_business_overview',
        'research_strengths',
        'research_weaknesses',
        'research_selection_flow',
        'research_interview_questions',
        'research_latest_topics',
        'research_generated_at',
        'topics_fetched_at',
        'search_suggestions_html',
        'source_links',
        'topics_search_suggestions_html',
        'topics_source_links',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'research_generated_at' => 'datetime',
            'topics_fetched_at' => 'datetime',
            'source_links' => 'array',
            'topics_source_links' => 'array',
        ];
    }

    /**
     * 企業名の正規化。二重登録の判定（user_id と normalized_company_name の一意制約）に使う。
     */
    public static function normalizeName(string $name): string
    {
        return trim($name);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loginInfo(): HasOne
    {
        return $this->hasOne(CompanyLoginInfo::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SelectionStatusHistory::class);
    }
}
