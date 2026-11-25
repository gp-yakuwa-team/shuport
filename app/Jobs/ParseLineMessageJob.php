<?php

namespace App\Jobs;

use App\Models\AiUsageLog;
use App\Models\PendingLineEvent;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\Ai\AiServiceException;
use App\Support\Ai\Prompt;
use App\Support\ErrorRecorder;
use App\Support\Line\LineApiService;
use App\Support\Line\LineMessages;
use App\Support\Line\LineTextPreprocessor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * LINE のテキストメッセージの解析（BG-04）。
 * 入力は利用者ID・cache のキー・受信日時の3つだけ。本文と replyToken は
 * cache（line:msg:{key}、120秒・暗号化）から取り出して消す。
 */
class ParseLineMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * ジョブ全体の上限は30秒（4.10 3.4）。
     */
    public int $timeout = 30;

    /**
     * 再投入しない（4.10 3.4）。失敗は failed_jobs に残す。
     */
    public int $tries = 1;

    public function __construct(
        public string $userId,
        public string $lineMsgKey,
        public string $receivedAt,
    ) {}

    public function handle(LineApiService $line, AiGateway $gateway): void
    {
        if ($this->budgetExpired()) {
            return;
        }

        $payload = $this->pullPayload();

        if ($payload === null) {
            return;
        }

        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $prepared = (new LineTextPreprocessor)->prepare($payload['text']);

        try {
            $result = $gateway->generateResult(
                'LINEのトーク解析',
                Prompt::render('P-05', 'line_parse', ['text' => $prepared['text']]),
                12,
                8,
            );
        } catch (AiServiceException $e) {
            ErrorRecorder::record($e, '外部サービス');
            $this->reply($line, $payload['reply_token'], LineMessages::PARSE_FAILED);

            return;
        }

        $this->recordUsage($user, $result->model, $result->inputTokens, $result->outputTokens);

        $data = $this->normalize($result->data, $prepared['urls']);

        if ($data === null) {
            $this->reply($line, $payload['reply_token'], LineMessages::PARSE_FAILED);

            return;
        }

        $pending = DB::transaction(function () use ($user, $data): PendingLineEvent {
            PendingLineEvent::query()->where('user_id', $user->id)->delete();

            return PendingLineEvent::create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'],
                'event_type' => $data['event_type'],
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'meeting_url' => $data['meeting_url'],
                'memo' => $data['memo'],
                'expires_at' => now()->addDay(),
            ]);
        });

        $this->replyConfirmation($line, $payload['reply_token'], $pending);
    }

    private function budgetExpired(): bool
    {
        try {
            $received = CarbonImmutable::parse($this->receivedAt);
        } catch (Throwable) {
            $this->record('line_received_at_invalid');

            return true;
        }

        if ($received->diffInSeconds(now(), true) > (int) config('line.reply_token_budget_seconds', 55)) {
            $this->record('line_reply_token_expired');

            return true;
        }

        return false;
    }

    /**
     * @return array{text: string, reply_token: string}|null
     */
    private function pullPayload(): ?array
    {
        $cached = Cache::pull('line:msg:'.$this->lineMsgKey);

        if (! is_string($cached)) {
            $this->record('line_text_cache_miss');

            return null;
        }

        try {
            $decoded = json_decode(
                Crypt::decryptString($cached),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (Throwable) {
            $this->record('line_text_cache_miss');

            return null;
        }

        if (! is_array($decoded)
            || ! isset($decoded['text'], $decoded['reply_token'])
            || ! is_string($decoded['text'])
            || ! is_string($decoded['reply_token'])) {
            $this->record('line_text_cache_miss');

            return null;
        }

        return ['text' => $decoded['text'], 'reply_token' => $decoded['reply_token']];
    }

    /**
     * R-P05-01〜07。DB の制約に合わない内容は、切り詰めずに失敗にする。
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $urls
     * @return array{company_name: string|null, event_type: string, start_at: CarbonImmutable, end_at: CarbonImmutable|null, meeting_url: string|null, memo: string|null}|null
     */
    private function normalize(array $data, array $urls): ?array
    {
        if (($data['event_found'] ?? null) !== true) {
            return null;
        }

        $eventType = $data['event_type'] ?? null;

        if (! is_string($eventType) || ! in_array($eventType, ['面接', 'ES締切', '説明会', 'その他'], true)) {
            return null;
        }

        if (($data['date_known'] ?? null) !== true) {
            return null;
        }

        $timeKnown = ($data['time_known'] ?? null) === true;
        $start = $this->parseDateTime($data['start_at'] ?? null);

        if ($start === null) {
            return null;
        }

        if (! $timeKnown) {
            // ES締切だけは時刻が不明でも、その日の終わりとして登録する。
            if ($eventType !== 'ES締切') {
                return null;
            }

            $start = $start->startOfDay()->setTime(23, 59, 59);
        }

        $end = null;
        $endRaw = $data['end_at'] ?? null;

        if ($endRaw !== null) {
            $end = $this->parseDateTime($endRaw);

            if ($end === null || $end->lessThanOrEqualTo($start)) {
                return null;
            }
        }

        $companyRaw = $data['company_name'] ?? null;

        if ($companyRaw !== null && ! is_string($companyRaw)) {
            return null;
        }

        $companyName = $this->stringOrNull($companyRaw);

        if ($companyName !== null && mb_strlen($companyName) > 100) {
            return null;
        }

        $memoRaw = $data['memo'] ?? null;

        if ($memoRaw !== null && ! is_string($memoRaw)) {
            return null;
        }

        $memo = $this->stringOrNull($memoRaw);

        if ($memo !== null) {
            $memo = preg_replace('/\[(?:EMAIL|PHONE|POSTAL|URL\d+)\]/u', '', $memo) ?? $memo;
            $memo = trim(preg_replace('/\s+/u', ' ', $memo) ?? $memo);

            if ($memo === '') {
                $memo = null;
            } elseif (mb_strlen($memo) > 1000) {
                return null;
            }
        }

        $urlRef = $data['meeting_url_ref'] ?? null;
        $meetingUrl = is_string($urlRef) && isset($urls[$urlRef]) ? $urls[$urlRef] : null;

        if ($meetingUrl !== null) {
            $scheme = parse_url($meetingUrl, PHP_URL_SCHEME);

            if (filter_var($meetingUrl, FILTER_VALIDATE_URL) === false || $scheme !== 'https') {
                $meetingUrl = null;
            }
        }

        return [
            'company_name' => $companyName,
            'event_type' => $eventType,
            'start_at' => $start,
            'end_at' => $end,
            'meeting_url' => $meetingUrl,
            'memo' => $memo,
        ];
    }

    private function parseDateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+09:00$/', $value) !== 1) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('Y-m-d\TH:i:sP', $value, 'Asia/Tokyo');

        if ($parsed === false || $parsed->format('Y-m-d\TH:i:sP') !== $value) {
            return null;
        }

        return $parsed;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function recordUsage(User $user, string $model, ?int $inputTokens, ?int $outputTokens): void
    {
        $log = AiUsageLog::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'LINEのトーク解析')
            ->orderByDesc('used_at')
            ->first();

        $log?->update([
            'model_name' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);
    }

    private function reply(LineApiService $line, string $replyToken, string $text): void
    {
        try {
            $line->reply($replyToken, [LineApiService::textMessage($text)]);
        } catch (Throwable $e) {
            ErrorRecorder::record($e, '外部サービス', 'line.api');
        }
    }

    private function replyConfirmation(LineApiService $line, string $replyToken, PendingLineEvent $pending): void
    {
        $message = LineApiService::textMessage(LineMessages::confirmation($pending));
        $message['quickReply'] = [
            'items' => [
                ['type' => 'action', 'action' => ['type' => 'message', 'label' => 'はい', 'text' => 'はい']],
                ['type' => 'action', 'action' => ['type' => 'message', 'label' => 'いいえ', 'text' => 'いいえ']],
            ],
        ];

        try {
            $line->reply($replyToken, [$message]);
        } catch (Throwable $e) {
            ErrorRecorder::record($e, '外部サービス', 'line.api');
        }
    }

    private function record(string $code): void
    {
        ErrorRecorder::record(new RuntimeException($code), 'バックグラウンド処理');
    }

    /**
     * 再投入しないため、DB などの想定外の失敗はここで記録して failed_jobs に残す。
     */
    public function failed(?Throwable $e): void
    {
        if ($e === null) {
            return;
        }

        ErrorRecorder::record($e, $e instanceof QueryException ? 'データベース' : 'バックグラウンド処理');
    }
}
