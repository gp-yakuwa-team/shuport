<?php

namespace App\Support\Ai;

/**
 * AI の応答本文と、実際に応答したモデル・トークン数。
 * ai_usage_logs の model_name とトークン数を、切り替え後のモデルで記録するために返す。
 */
final readonly class AiResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public string $model,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
