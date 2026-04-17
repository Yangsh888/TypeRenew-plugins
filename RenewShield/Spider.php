<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Spider
{
    private const MAP = [
        'googlebot' => ['ua' => 'Googlebot', 'suffixes' => ['.googlebot.com', '.google.com']],
        'bingbot' => ['ua' => 'bingbot', 'suffixes' => ['.search.msn.com']],
        'baiduspider' => ['ua' => 'Baiduspider', 'suffixes' => ['.baidu.com', '.baidu.jp']],
    ];

    public static function detect(string $ua): string
    {
        foreach (self::MAP as $key => $meta) {
            if (stripos($ua, (string) $meta['ua']) !== false) {
                return $key;
            }
        }

        return '';
    }

    public static function verify(string $name, string $ip, int $ttlHours = 24): bool
    {
        if ($name === '' || $ip === '') {
            return false;
        }

        $cacheKey = 'spider:' . $name . ':' . sha1($ip);
        $cached = State::get($cacheKey, null);
        if (is_array($cached) && isset($cached['ok'])) {
            return (bool) $cached['ok'];
        }

        $ok = false;
        try {
            $ok = self::verifyNow($name, $ip);
        } catch (\Throwable $e) {
            Log::write('spider', 'verify', 'observe', 'spider.error', 0, $e->getMessage(), ['name' => $name, 'ip' => $ip]);
        }

        State::set($cacheKey, ['ok' => $ok], max(1, $ttlHours) * 3600);
        return $ok;
    }

    private static function verifyNow(string $name, string $ip): bool
    {
        $meta = self::MAP[$name] ?? null;
        if (!$meta || filter_var($ip, FILTER_VALIDATE_IP) === false || !function_exists('gethostbyaddr')) {
            return false;
        }

        $host = strtolower((string) gethostbyaddr($ip));
        if ($host === '' || $host === $ip || !self::hostAllowed($host, (array) $meta['suffixes'])) {
            return false;
        }

        $resolved = [];
        if (function_exists('gethostbynamel')) {
            $a = gethostbynamel($host);
            if (is_array($a)) {
                $resolved = array_merge($resolved, $a);
            }
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ipv6'])) {
                        $resolved[] = (string) $record['ipv6'];
                    }
                }
            }
        }

        $resolved = array_values(array_unique(array_filter($resolved)));
        return in_array($ip, $resolved, true);
    }

    private static function hostAllowed(string $host, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
