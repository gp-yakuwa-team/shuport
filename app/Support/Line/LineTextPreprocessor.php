<?php

namespace App\Support\Line;

/**
 * LINE のトーク本文を AI に渡す前に整える（4.11 2.4.1）。
 * 本文は1,000字まで。URL は [URLn] に退避し、メール・電話・郵便番号だけを置換する。
 * 日時の表現は読み取りに必要なので置換しない。
 */
final class LineTextPreprocessor
{
    private const MAX_LENGTH = 1000;

    /**
     * @return array{text: string, urls: array<string, string>}
     */
    public function prepare(string $text): array
    {
        $text = mb_substr($text, 0, self::MAX_LENGTH);
        $urls = [];

        $masked = preg_replace_callback(
            '~https?://[^\s<>"\[\]]+~u',
            function (array $matches) use (&$urls): string {
                $url = preg_replace('/[。、，．）」』】]+$/u', '', $matches[0]) ?? $matches[0];
                $key = 'URL'.(count($urls) + 1);
                $urls[$key] = $url;

                return '['.$key.']';
            },
            $text,
        );

        $masked = $masked ?? $text;
        $masked = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/u', '[EMAIL]', $masked) ?? $masked;
        $masked = preg_replace('/(?<!\d)0\d{1,4}-?\d{1,4}-?\d{3,4}(?!\d)/u', '[PHONE]', $masked) ?? $masked;
        $masked = preg_replace('/〒\s*\d{3}-?\d{4}/u', '[POSTAL]', $masked) ?? $masked;

        return ['text' => $masked, 'urls' => $urls];
    }
}
