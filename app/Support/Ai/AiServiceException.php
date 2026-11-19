<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * AI サービスの呼び出しに失敗したときに投げる例外。
 * 失敗の詳細（プロバイダの応答本文など）は利用者向けの応答には出さない。
 */
class AiServiceException extends RuntimeException {}
