<?php

namespace App\Support\Line;

use App\Jobs\ParseLineMessageJob;
use App\Models\Company;
use App\Models\LineOneTimeCode;
use App\Models\PendingLineEvent;
use App\Models\User;
use App\Support\Ai\AiGateway;
use App\Support\AiUsageGate;
use App\Support\ErrorRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * LINE Webhook の1イベントを処理する（4.12 2.2〜2.4）。
 * 連携コードの照合と「はい」「いいえ」の確認は AI を使わないため、その場で返信する。
 * 連携済みの自由文だけを、回数・休止を確かめてから ParseLineMessageJob へ渡す。
 */
class LineWebhookProcessor
{
    public function __construct(
        private readonly LineApiService $line,
        private readonly AiGateway $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        if (! $this->isHandledEvent($event)) {
            return;
        }

        $eventId = $this->eventId($event);

        if ($eventId !== null && ! Cache::add('line:evt:'.hash('sha256', $eventId), 1, 86400)) {
            return;
        }

        $lineUserId = $event['source']['userId'] ?? null;
        $text = $event['message']['text'] ?? null;
        $replyToken = $event['replyToken'] ?? null;

        if (! is_string($lineUserId) || ! is_string($text) || ! is_string($replyToken)) {
            return;
        }

        $user = User::query()->where('line_user_id', $lineUserId)->first();

        if ($user === null) {
            $this->handleUnlinked($lineUserId, $text, $replyToken);

            return;
        }

        if ($text === 'はい' || $text === 'いいえ') {
            $this->handleConfirmation($user, $text, $replyToken);

            return;
        }

        // 連携済みの6桁の数字だけは、コードとしても予定としても扱わない（回数も使わない）。
        if (preg_match('/^\d{6}$/', $text) === 1) {
            return;
        }

        $this->acceptParse($user, $text, $replyToken);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function isHandledEvent(array $event): bool
    {
        return ($event['type'] ?? null) === 'message'
            && ($event['message']['type'] ?? null) === 'text'
            && ($event['source']['type'] ?? null) === 'user';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function eventId(array $event): ?string
    {
        $id = $event['webhookEventId'] ?? $event['message']['id'] ?? null;

        if (is_string($id) && $id !== '') {
            return $id;
        }

        $encoded = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : null;
    }

    private function handleUnlinked(string $lineUserId, string $text, string $replyToken): void
    {
        if (preg_match('/^\d{6}$/', $text) !== 1) {
            $this->reply($replyToken, LineMessages::NOT_LINKED);

            return;
        }

        $rateKey = 'line:code:'.hash('sha256', $lineUserId);

        if (RateLimiter::tooManyAttempts($rateKey, (int) config('line.code_attempt_limit', 5))) {
            $this->reply($replyToken, LineMessages::CODE_LOCKED);

            return;
        }

        $pepper = (string) (config('line.link_code_pepper') ?: config('app.key'));
        $hash = hash_hmac('sha256', $text, $pepper);

        $code = LineOneTimeCode::query()
            ->where('code_hash', $hash)
            ->where('expires_at', '>', now())
            ->first();

        if ($code === null) {
            RateLimiter::hit($rateKey, (int) config('line.code_attempt_decay_seconds', 600));
            $this->reply($replyToken, LineMessages::CODE_INVALID);

            return;
        }

        DB::transaction(function () use ($code, $lineUserId): void {
            User::query()
                ->where('line_user_id', $lineUserId)
                ->whereKeyNot($code->user_id)
                ->update(['line_user_id' => null]);

            User::query()->whereKey($code->user_id)->update(['line_user_id' => $lineUserId]);
            $code->delete();
        });

        RateLimiter::clear($rateKey);
        $this->reply($replyToken, LineMessages::LINK_COMPLETED);
    }

    private function handleConfirmation(User $user, string $text, string $replyToken): void
    {
        $pending = PendingLineEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('expires_at')
            ->first();

        if ($pending === null || $pending->expires_at->isPast()) {
            $this->reply($replyToken, LineMessages::EXPIRED);

            return;
        }

        if ($text === 'いいえ') {
            $pending->delete();
            $this->reply($replyToken, LineMessages::CANCELLED);

            return;
        }

        $company = null;

        if ($pending->company_name !== null && $pending->company_name !== '') {
            $company = Company::query()
                ->where('user_id', $user->id)
                ->where('normalized_company_name', Company::normalizeName($pending->company_name))
                ->first();
        }

        $eventType = $pending->event_type;
        $companyName = $pending->company_name;
        $title = $companyName !== null && $companyName !== ''
            ? $companyName.' '.$eventType
            : $eventType;

        if (mb_strlen($title) > 50) {
            $suffix = ' '.$eventType;
            $title = mb_substr($companyName ?? '', 0, 50 - mb_strlen($suffix)).$suffix;
        }

        DB::transaction(function () use ($pending, $user, $company, $companyName, $title): void {
            DB::table('events')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'company_id' => $company?->id,
                'company_name' => $companyName,
                'title' => $title,
                'event_type' => $pending->event_type,
                'start_at' => $pending->start_at,
                'end_at' => $pending->end_at,
                'meeting_url' => $pending->meeting_url,
                'memo' => $pending->memo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $pending->delete();
        });

        $this->reply($replyToken, LineMessages::REGISTERED);
    }

    private function acceptParse(User $user, string $text, string $replyToken): void
    {
        if (! $this->gateway->isAvailable()) {
            $this->reply($replyToken, LineMessages::SUSPENDED);

            return;
        }

        $withinLimit = $user->is_demo
            ? AiUsageGate::demoAllowed()
            : AiUsageGate::generationAllowed($user->id);

        if (! $withinLimit) {
            $this->reply($replyToken, LineMessages::LIMIT);

            return;
        }

        AiUsageGate::record($user->id, (string) config('ai.gemini.model'), 'LINEのトーク解析');

        $key = (string) Str::uuid();
        $payload = json_encode(
            ['text' => $text, 'reply_token' => $replyToken],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        Cache::put(
            'line:msg:'.$key,
            Crypt::encryptString($payload),
            (int) config('line.message_cache_ttl_seconds', 120),
        );

        ParseLineMessageJob::dispatch(
            $user->id,
            $key,
            now('Asia/Tokyo')->toIso8601String(),
        );
    }

    private function reply(string $replyToken, string $text): void
    {
        try {
            $this->line->reply($replyToken, [LineApiService::textMessage($text)]);
        } catch (Throwable $e) {
            ErrorRecorder::record($e, '外部サービス', 'line.api');
        }
    }
}
