<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 開発環境専用のサンプルデータ（画面の表示確認用）。
 *
 * 実行方法:
 * sail artisan db:seed --class=LocalDevSeeder
 *
 * データの付け先の利用者は、次の順で決まります。
 * 1. 環境変数 LOCAL_SEED_USER_ID で指定した利用者（自分のアカウントに付けたい場合はこれ）
 * 2. 既にいる利用者のうち、最も古い1件
 * 3. 利用者が1人もいない場合は、このシーダー用に1件作る
 *
 * 本番・共有の DB では誤って実行しないよう、local 環境以外では何もしません。
 * 体験用アカウントの初期化（DemoDataSeeder / shuport:reset-demo-data）とは別物です。
 */
class LocalDevSeeder extends Seeder
{
    /**
     * サンプルの企業データ（企業名、業界、選考ステータス）。
     * 企業名は架空のものです。
     *
     * @var list<array{name: string, industry: string, status: string, researched: bool}>
     */
    private array $samples = [
        ['name' => '株式会社ノースブリッジ', 'industry' => 'IT・ソフトウェア・通信', 'status' => '一次面接', 'researched' => true],
        ['name' => 'ミナト自動車株式会社', 'industry' => 'メーカー（機械・電機・自動車）', 'status' => '書類選考中', 'researched' => true],
        ['name' => '株式会社はるかぜフーズ', 'industry' => 'メーカー（食品・日用品・医薬品）', 'status' => 'エントリー済み', 'researched' => false],
        ['name' => 'さくら総合商事株式会社', 'industry' => '商社', 'status' => '検討中', 'researched' => false],
        ['name' => '株式会社レイクサイド銀行', 'industry' => '金融（銀行・証券・保険）', 'status' => '最終面接', 'researched' => true],
        ['name' => '株式会社ことのは広告', 'industry' => '広告・出版・マスコミ', 'status' => 'お見送り', 'researched' => false],
        ['name' => 'つばさコンサルティング株式会社', 'industry' => 'コンサルティング', 'status' => '内定', 'researched' => true],
        ['name' => '株式会社そらとぶホテルズ', 'industry' => 'ホテル・旅行・外食・レジャー', 'status' => '検討中', 'researched' => false],
    ];

    public function run(): void
    {
        if (! App::environment('local')) {
            $this->command?->warn('LocalDevSeeder は local 環境でのみ実行できます。スキップしました。');

            return;
        }

        $user = $this->resolveUser();

        foreach ($this->samples as $sample) {
            $this->upsertCompany($user, $sample);
        }

        $this->command?->info('LocalDevSeeder: サンプル企業 '.count($this->samples).' 件を利用者 '.$user->id.' に登録しました。');
    }

    private function resolveUser(): User
    {
        $userId = config('shuport.local_seed_user_id');

        if (is_string($userId) && $userId !== '') {
            $user = User::query()->find($userId);

            if ($user === null) {
                throw new RuntimeException('LOCAL_SEED_USER_ID で指定された利用者が見つかりません: '.$userId);
            }

            return $user;
        }

        $user = User::query()->oldest('created_at')->first();

        if ($user !== null) {
            return $user;
        }

        $user = new User;
        $user->terms_accepted_at = now();
        $user->terms_version = 'local-dev';
        $user->is_demo = false;
        $user->save();

        return $user;
    }

    /**
     * @param  array{name: string, industry: string, status: string, researched: bool}  $sample
     */
    private function upsertCompany(User $user, array $sample): void
    {
        $company = Company::query()
            ->where('user_id', $user->id)
            ->where('normalized_company_name', $sample['name'])
            ->first() ?? new Company;

        $company->id ??= (string) Str::uuid();
        $company->user_id = $user->id;
        $company->company_name = $sample['name'];
        $company->normalized_company_name = $sample['name'];
        $company->industry = $sample['industry'];
        $company->selection_status = $sample['status'];
        $company->job_info_text = '新卒採用：総合職。若干名。勤務地：本社および全国の事業所。';

        if ($sample['researched']) {
            $company->research_business_overview = '企業研究の事業概要が入る場所の表示確認用テキストです。';
            $company->research_strengths = '強みの表示確認用テキストです。';
            $company->research_weaknesses = '弱みの表示確認用テキストです。';
            $company->research_selection_flow = 'ES → Webテスト → 一次面接 → 最終面接 → 内定';
            $company->research_interview_questions = '想定質問の表示確認用テキストです。';
            $company->research_latest_topics = '最新トピックスの表示確認用テキストです。';
            $company->research_generated_at = now();
        }

        $company->save();
    }
}
