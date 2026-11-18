<?php

namespace App\Support\Supabase;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use stdClass;
use Throwable;

class JwtVerifier
{
    /**
     * Supabase Auth のアクセストークン（JWT）を、ネットワーク越しでなく
     * ローカルで検証し、クレームを返す（基本設計書 v16 6.1）。
     * 署名・期限・発行者・対象者が正しくなければ例外を投げる（呼び出し側で 401 にする）。
     */
    public function verify(string $token): stdClass
    {
        $claims = JWT::decode($token, $this->keySet());

        $issuer = rtrim((string) config('supabase.url'), '/').'/auth/v1';
        if (($claims->iss ?? null) !== $issuer) {
            throw new InvalidArgumentException('トークンの発行者が一致しません');
        }
        if (($claims->aud ?? null) !== 'authenticated') {
            throw new InvalidArgumentException('トークンの対象者が一致しません');
        }

        return $claims;
    }

    /**
     * セッションの追加確認（API-22・23・38 の対象操作）:
     * トークン内の session_id が auth.sessions に存在するかを確かめる（DB定義書 v7）。
     */
    public function sessionExists(stdClass $claims): bool
    {
        $sessionId = $claims->session_id ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return false;
        }

        return DB::table('auth.sessions')->where('id', $sessionId)->exists();
    }

    /**
     * 検証に使う鍵。プロジェクトの JWKS（公開鍵）を優先し、
     * JWKS が無い場合は旧式の対称鍵（HS256）に切り替える。
     *
     * @return array<string, Key>|Key
     */
    private function keySet(): array|Key
    {
        $jwks = $this->jwks();
        if ($jwks !== []) {
            return JWK::parseKeySet($jwks);
        }

        $secret = config('supabase.jwt_secret');
        if (is_string($secret) && $secret !== '') {
            return new Key($secret, 'HS256');
        }

        throw new InvalidArgumentException('検証用の鍵が設定されていません');
    }

    /**
     * プロジェクトの JWKS を取得し、キャッシュする。
     *
     * @return array<string, mixed>
     */
    private function jwks(): array
    {
        $url = rtrim((string) config('supabase.url'), '/').'/auth/v1/.well-known/jwks.json';
        $ttl = config('supabase.jwks_cache_ttl', 600);

        try {
            $cached = Cache::remember('supabase.jwks', is_int($ttl) ? $ttl : 600, function () use ($url) {
                $response = Http::timeout(5)->get($url);
                if (! $response->successful()) {
                    return [];
                }
                $keys = $response->json('keys');

                return is_array($keys) ? ['keys' => $keys] : [];
            });
        } catch (Throwable) {
            return [];
        }

        return is_array($cached) ? $cached : [];
    }
}
