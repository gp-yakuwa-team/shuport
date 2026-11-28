<?php

namespace App\Models;

use App\Models\Concerns\FormatsDatesForApi;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * 企業（DB定義書 v7 companies）。
 * 業界は確定17区分、選考ステータスは確定9種（DB のチェック制約と同じ）。
 */
class Company extends Model
{
    use FormatsDatesForApi;
    use HasUuids;

    /** @var list<string> */
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

    /** @var list<string> */
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

    protected $guarded = [];

    /**
     * source_links・topics_source_links は jsonb（URL の一覧）。配列で読み書きする。
     *
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
     * 二重登録の判定に使う正規化済み企業名（前後の空白を取り除く）。
     * 表記ゆれなどの曖昧な一致は検出しない（API定義書 v18 6.3）。
     */
    public static function normalizeName(string $name): string
    {
        return trim($name);
    }
}
