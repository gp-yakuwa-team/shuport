<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Encryption\Encrypter;

/**
 * 採用サイトログイン情報（company_login_infos の ID・パスワード）を、
 * APP_KEY とは別の専用の鍵（RECRUIT_SITE_ENCRYPTION_KEY）で暗号化・復号する
 * （3.4 セキュリティ設計 3-1・3-2）。AES-256-CBC（改ざん検知付き）。
 */
class LoginInfoCipher
{
    private const CIPHER = 'aes-256-cbc';

    public function encrypt(#[\SensitiveParameter] string $value): string
    {
        $encrypter = $this->encrypter();

        try {
            return $encrypter->encryptString($value);
        } catch (EncryptException) {
            throw new LoginInfoCipherException('login_info_encrypt_failed');
        }
    }

    public function decrypt(#[\SensitiveParameter] string $payload): string
    {
        $encrypter = $this->encrypter();

        try {
            return $encrypter->decryptString($payload);
        } catch (DecryptException) {
            throw new LoginInfoCipherException('login_info_decrypt_failed');
        }
    }

    private function encrypter(): Encrypter
    {
        $key = config('shuport.recruit_site_encryption_key');

        if (! is_string($key) || $key === '') {
            throw new LoginInfoCipherException('login_info_key_missing');
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }

        if (! is_string($key) || ! Encrypter::supported($key, self::CIPHER)) {
            throw new LoginInfoCipherException('login_info_key_invalid');
        }

        return new Encrypter($key, self::CIPHER);
    }
}
