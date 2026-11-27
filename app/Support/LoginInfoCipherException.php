<?php

namespace App\Support;

use RuntimeException;

/**
 * 採用サイトログイン情報の暗号化・復号ができなかったときの例外。
 * メッセージには理由のコードだけを入れ、平文・暗号文・鍵は含めない。
 */
class LoginInfoCipherException extends RuntimeException {}
