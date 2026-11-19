<?php

namespace App\Support\Line;

use RuntimeException;

/**
 * LINE Messaging API の呼び出しに失敗したときに投げる例外。
 */
class LineApiException extends RuntimeException {}
