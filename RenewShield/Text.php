<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Text
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function cut(?string $value, int $max): string
    {
        $value = trim(str_replace("\0", '', (string) $value));
        if ($max <= 0 || $value === '') {
            return $max <= 0 ? '' : $value;
        }

        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $max);
        }

        if (function_exists('iconv_substr')) {
            $result = iconv_substr($value, 0, $max, 'UTF-8');
            if ($result !== false) {
                return (string) $result;
            }
        }

        return substr($value, 0, $max);
    }

    public static function lines(string $value, int $maxLen = 255, int $maxLines = 200): array
    {
        $lines = preg_split('/\R/u', $value) ?: [];
        $clean = [];

        foreach ($lines as $line) {
            $line = trim(str_replace("\0", '', (string) $line));
            if ($line === '') {
                continue;
            }

            $clean[] = self::cut($line, $maxLen);
            if (count($clean) >= $maxLines) {
                break;
            }
        }

        return array_values(array_unique($clean));
    }

    public static function time(int $timestamp): string
    {
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '-';
    }
}
