<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * 4.11 のプロンプトを読み、封筒（task・schema_version・today_jst・input）を付けて返す。
 * 本文は resources/prompts/{番号}/{版}/system.txt、版は config/ai.php で管理する。
 */
final class Prompt
{
    /**
     * @param  array<string, mixed>  $input
     */
    public static function render(string $id, string $task, array $input): string
    {
        $version = config("ai.prompts.versions.{$id}");

        if (! is_string($version) || $version === '') {
            throw new RuntimeException("Prompt version is not configured: {$id}");
        }

        $path = resource_path("prompts/{$id}/{$version}/system.txt");

        if (! is_file($path)) {
            throw new RuntimeException("Prompt file is missing: {$path}");
        }

        $system = file_get_contents($path);

        if ($system === false) {
            throw new RuntimeException("Prompt file is not readable: {$path}");
        }

        $envelope = [
            'task' => $task,
            'schema_version' => (string) config('ai.prompts.schema_version', '1.0'),
            'today_jst' => now('Asia/Tokyo')->format('Y-m-d'),
            'input' => $input,
        ];

        return rtrim($system)."\n\n# 入力\n".json_encode(
            $envelope,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
