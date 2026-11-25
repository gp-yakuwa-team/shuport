<?php

namespace App\Support\Line;

use App\Models\PendingLineEvent;

/**
 * LINE への返信文（4.12 の MSG-LINE-001〜008）。
 * 画面の文言と混ざらないよう、LINE の返信はここに集める。
 */
final class LineMessages
{
    public const LINK_COMPLETED = '連携が完了しました。今後、選考予定と締切をお知らせします。';

    public const CODE_INVALID = '連携コードが正しくないか、有効期限が切れています';

    public const CODE_LOCKED = 'コードの入力が一時的にロックされています。10分ほどおいてから再度お試しください';

    public const NOT_LINKED = 'ShuPortとの連携が完了していません。Webの設定画面（No.10）で発行した6桁の連携コードを送信してください';

    public const PARSE_FAILED = '日程を取得できませんでした。内容を確認してください';

    public const LIMIT = '読み取りの回数の上限に達しました。時間をおいて送るか、Webのカレンダーから登録してください';

    public const SUSPENDED = 'ただいま日程の自動読み取りを停止しています。Webのカレンダーから登録してください';

    public const CANCELLED = '登録を取り消しました';

    public const EXPIRED = '確認の有効期限が切れました。もう一度送信してください';

    public const REGISTERED = '予定を登録しました';

    /**
     * 承認待ちの内容を、null の項目と区切りを出さずに組み立てる。
     */
    public static function confirmation(PendingLineEvent $pending): string
    {
        $lines = ['予定を登録しますか？', ''];

        if ($pending->company_name !== null && $pending->company_name !== '') {
            $lines[] = '企業名：'.$pending->company_name;
        }

        $lines[] = '種別：'.$pending->event_type;
        $lines[] = '日時：'.self::period($pending);

        if ($pending->meeting_url !== null && $pending->meeting_url !== '') {
            $lines[] = 'URL：'.$pending->meeting_url;
        }

        if ($pending->memo !== null && $pending->memo !== '') {
            $lines[] = 'メモ：'.$pending->memo;
        }

        $lines[] = '';
        $lines[] = '「はい」で登録、「いいえ」で取り消します。';

        return implode("\n", $lines);
    }

    private static function period(PendingLineEvent $pending): string
    {
        $start = $pending->start_at->timezone('Asia/Tokyo');
        $date = $start->format('Y年n月j日');

        if ($pending->end_at === null) {
            return $date.' '.$start->format('H:i');
        }

        $end = $pending->end_at->timezone('Asia/Tokyo');

        if ($end->isSameDay($start)) {
            return $date.' '.$start->format('H:i').'〜'.$end->format('H:i');
        }

        return $date.' '.$start->format('H:i').'〜'.$end->format('Y年n月j日 H:i');
    }
}
