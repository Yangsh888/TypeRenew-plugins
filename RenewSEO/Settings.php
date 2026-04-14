<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

use Typecho\Cache;
use Typecho\Common;
use Utils\Helper;
use Utils\Pref;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Settings
{
    private const NAME = 'RenewSEO';
    private const CACHE_KEY = 'renewseo:settings:v1';

    private static ?array $runtime = null;

    public static function load(): array
    {
        return Pref::load(
            self::$runtime,
            self::CACHE_KEY,
            self::defaults(),
            static function (): array {
                try {
                    return (array) Helper::options()->plugin(self::NAME)->toArray();
                } catch (\Throwable $e) {
                    self::report('load.read', $e);
                    return [];
                }
            },
            [self::class, 'normalize'],
            [self::class, 'ensureStored'],
            [self::class, 'report']
        );
    }

    public static function defaults(): array
    {
        return [
            'enabled' => '1',
            'cacheTtl' => 300,
            'panelSize' => 40,
            'logKeepDays' => 30,
            'notFoundKeepDays' => 30,
            'logAutoClean' => '1',
            'pushAsync' => '1',
            'pushTimeout' => 4,
            'robotsEnable' => '1',
            'robotsDefault' => 'allow',
            'robotsMode' => 'default_only',
            'robotsAllowed' => "Baiduspider\nGooglebot\nbingbot\nBytespider",
            'robotsDenied' => '',
            'robotsBlocked' => "/admin/\n/install.php",
            'robotsExtra' => '',
            'robotsSitemap' => '1',
            'robotsCustomSitemaps' => '',
            'sitemapEnable' => '1',
            'sitemapTxt' => '1',
            'sitemapSplit' => 1000,
            'sitemapDebounce' => 15,
            'sitemapPage' => '1',
            'sitemapCategory' => '1',
            'sitemapTag' => '1',
            'sitemapAuthor' => '0',
            'sitemapPriorityHome' => '1.0',
            'sitemapPriorityPost' => '0.8',
            'sitemapPriorityPage' => '0.7',
            'sitemapPriorityCategory' => '0.6',
            'sitemapPriorityTag' => '0.5',
            'sitemapPriorityAuthor' => '0.4',
            'sitemapFreqHome' => 'daily',
            'sitemapFreqPost' => 'weekly',
            'sitemapFreqPage' => 'monthly',
            'sitemapFreqTaxonomy' => 'daily',
            'ogEnable' => '1',
            'ogDefaultImage' => '',
            'timeEnable' => '1',
            'canonicalEnable' => '1',
            'canonicalStrip' => 'utm_source,utm_medium,utm_campaign,utm_term,utm_content,spm,from,ref',
            'noindexSearch' => '1',
            'noindex404' => '1',
            'altEnable' => '1',
            'altTemplate' => '{title} - {site}',
            'baiduEnable' => '0',
            'baiduToken' => '',
            'baiduQuick' => '0',
            'baiduDays' => 0,
            'baiduPushOnEdit' => '0',
            'indexNowEnable' => '0',
            'indexNowKey' => '',
            'indexNowKeyPath' => '',
            'indexNowOnEdit' => '1',
            'bingEnable' => '0',
            'bingApiKey' => '',
            'bingOnEdit' => '1',
        ];
    }

    public static function normalize(array $settings): array
    {
        $d = self::defaults();
        $settings = array_merge($d, $settings);

        $settings['enabled'] = self::bool($settings['enabled'] ?? '1');
        $settings['logAutoClean'] = self::bool($settings['logAutoClean'] ?? '1');
        $settings['pushAsync'] = self::bool($settings['pushAsync'] ?? '1');
        $settings['robotsEnable'] = self::bool($settings['robotsEnable'] ?? '1');
        $settings['robotsSitemap'] = self::bool($settings['robotsSitemap'] ?? '1');
        $settings['sitemapEnable'] = self::bool($settings['sitemapEnable'] ?? '1');
        $settings['sitemapTxt'] = self::bool($settings['sitemapTxt'] ?? '1');
        $settings['sitemapPage'] = self::bool($settings['sitemapPage'] ?? '1');
        $settings['sitemapCategory'] = self::bool($settings['sitemapCategory'] ?? '1');
        $settings['sitemapTag'] = self::bool($settings['sitemapTag'] ?? '1');
        $settings['sitemapAuthor'] = self::bool($settings['sitemapAuthor'] ?? '0');
        $settings['ogEnable'] = self::bool($settings['ogEnable'] ?? '1');
        $settings['timeEnable'] = self::bool($settings['timeEnable'] ?? '1');
        $settings['canonicalEnable'] = self::bool($settings['canonicalEnable'] ?? '1');
        $settings['noindexSearch'] = self::bool($settings['noindexSearch'] ?? '1');
        $settings['noindex404'] = self::bool($settings['noindex404'] ?? '1');
        $settings['altEnable'] = self::bool($settings['altEnable'] ?? '1');
        $settings['baiduEnable'] = self::bool($settings['baiduEnable'] ?? '0');
        $settings['baiduQuick'] = self::bool($settings['baiduQuick'] ?? '0');
        $settings['baiduPushOnEdit'] = self::bool($settings['baiduPushOnEdit'] ?? '0');
        $settings['indexNowEnable'] = self::bool($settings['indexNowEnable'] ?? '0');
        $settings['indexNowOnEdit'] = self::bool($settings['indexNowOnEdit'] ?? '1');
        $settings['bingEnable'] = self::bool($settings['bingEnable'] ?? '0');
        $settings['bingOnEdit'] = self::bool($settings['bingOnEdit'] ?? '1');

        $settings['panelSize'] = self::int($settings['panelSize'] ?? 40, 10, 200, 40);
        $settings['cacheTtl'] = self::int($settings['cacheTtl'] ?? 300, 60, 3600, 300);
        $settings['logKeepDays'] = self::int($settings['logKeepDays'] ?? 30, 0, 3650, 30);
        $settings['notFoundKeepDays'] = self::int($settings['notFoundKeepDays'] ?? 30, 0, 3650, 30);
        $settings['pushTimeout'] = self::int($settings['pushTimeout'] ?? 4, 2, 20, 4);
        $settings['sitemapSplit'] = self::int($settings['sitemapSplit'] ?? 1000, 100, 50000, 1000);
        $settings['sitemapDebounce'] = self::int($settings['sitemapDebounce'] ?? 15, 0, 3600, 15);
        $settings['baiduDays'] = self::int($settings['baiduDays'] ?? 0, 0, 3650, 0);

        $settings['robotsDefault'] = in_array((string) $settings['robotsDefault'], ['allow', 'deny'], true)
            ? (string) $settings['robotsDefault'] : 'allow';
        $settings['robotsMode'] = in_array((string) $settings['robotsMode'], ['default_only', 'all'], true)
            ? (string) $settings['robotsMode'] : 'default_only';

        $settings['robotsAllowed'] = self::lines($settings['robotsAllowed'] ?? '', 64, 50);
        $settings['robotsDenied'] = self::lines($settings['robotsDenied'] ?? '', 64, 50);
        $settings['robotsBlocked'] = self::paths($settings['robotsBlocked'] ?? '', 100);
        $settings['robotsExtra'] = self::text($settings['robotsExtra'] ?? '', 16000);
        $settings['robotsCustomSitemaps'] = self::urls($settings['robotsCustomSitemaps'] ?? '', 20);
        $settings['ogDefaultImage'] = self::urlOrRelative($settings['ogDefaultImage'] ?? '', 1024);
        $settings['canonicalStrip'] = self::lines(str_replace(',', "\n", (string) ($settings['canonicalStrip'] ?? '')), 64, 50);
        $settings['altTemplate'] = self::text($settings['altTemplate'] ?? '{title} - {site}', 120);
        $settings['baiduToken'] = self::text($settings['baiduToken'] ?? '', 200);
        $settings['indexNowKey'] = self::token($settings['indexNowKey'] ?? '');
        $settings['indexNowKeyPath'] = self::relativeFile($settings['indexNowKeyPath'] ?? '');
        $settings['bingApiKey'] = self::text($settings['bingApiKey'] ?? '', 255);

        foreach ([
            'sitemapPriorityHome' => '1.0',
            'sitemapPriorityPost' => '0.8',
            'sitemapPriorityPage' => '0.7',
            'sitemapPriorityCategory' => '0.6',
            'sitemapPriorityTag' => '0.5',
            'sitemapPriorityAuthor' => '0.4',
        ] as $key => $default) {
            $settings[$key] = self::priority($settings[$key] ?? $default, $default);
        }

        foreach ([
            'sitemapFreqHome' => 'daily',
            'sitemapFreqPost' => 'weekly',
            'sitemapFreqPage' => 'monthly',
            'sitemapFreqTaxonomy' => 'daily',
        ] as $key => $default) {
            $settings[$key] = self::freq($settings[$key] ?? $default, $default);
        }

        return $settings;
    }

    public static function store(array $settings): void
    {
        $settings = self::normalize($settings);
        \Widget\Plugins\Edit::configPlugin(self::NAME, $settings);
        self::clear();
    }

    public static function ensureStored(): void
    {
        $raw = [];
        try {
            $raw = (array) Helper::options()->plugin(self::NAME)->toArray();
        } catch (\Throwable $e) {
            $raw = [];
            self::report('ensureStored.read', $e);
        }

        \Widget\Plugins\Edit::configPlugin(self::NAME, self::normalize(array_merge(self::defaults(), $raw)));
        self::clear();
    }

    public static function clear(): void
    {
        Pref::clear(self::CACHE_KEY, [self::class, 'report']);
        self::$runtime = null;
    }

    public static function options()
    {
        return Helper::options();
    }

    public static function cache(): Cache
    {
        return Cache::getInstance();
    }

    public static function panelUrl(): string
    {
        return Helper::url(self::NAME . '/Panel.php');
    }

    public static function assetUrl(string $path): string
    {
        $base = (string) (self::options()->pluginUrl ?? '');
        if ($base === '') {
            $base = Common::url('usr/plugins/', self::siteUrl());
        }

        return Common::url(self::NAME . '/' . ltrim($path, '/'), $base);
    }

    public static function actionUrl(string $do = '', bool $secure = false): string
    {
        $query = [];
        if ($do !== '') {
            $query['do'] = $do;
        }

        $path = '/action/renew-seo';
        if (!empty($query)) {
            $path .= '?' . http_build_query($query);
        }

        if ($secure) {
            return \Widget\Security::alloc()->getIndex($path);
        }

        return Common::url($path, (string) self::options()->index);
    }

    public static function siteUrl(): string
    {
        return rtrim((string) self::options()->siteUrl, '/') . '/';
    }

    public static function indexUrl(): string
    {
        return rtrim((string) self::options()->index, '/') . '/';
    }

    public static function siteHost(): string
    {
        return (string) (parse_url(self::siteUrl(), PHP_URL_HOST) ?? '');
    }

    public static function siteScheme(): string
    {
        return (string) (parse_url(self::siteUrl(), PHP_URL_SCHEME) ?? 'https');
    }

    public static function siteName(): string
    {
        return (string) (self::options()->title ?? 'TypeRenew');
    }

    public static function rootPath(string $relative = ''): string
    {
        $root = rtrim((string) __TYPECHO_ROOT_DIR__, '\\/');
        if ($relative === '') {
            return $root;
        }

        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative, '\\/'));
        return $root . DIRECTORY_SEPARATOR . $relative;
    }

    public static function rootUrl(string $relative = ''): string
    {
        return Common::url(ltrim(str_replace('\\', '/', $relative), '/'), self::siteUrl());
    }

    public static function absoluteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (strpos($url, '//') === 0) {
            return self::siteScheme() . ':' . $url;
        }

        return Common::url(ltrim($url, '/'), self::siteUrl());
    }

    public static function keyRelativePath(array $settings): string
    {
        $key = (string) ($settings['indexNowKey'] ?? '');
        if ($key === '') {
            return '';
        }

        $custom = trim((string) ($settings['indexNowKeyPath'] ?? ''));
        if ($custom !== '') {
            return ltrim(str_replace('\\', '/', $custom), '/');
        }

        return $key . '.txt';
    }

    public static function keyUrl(array $settings): string
    {
        $relative = self::keyRelativePath($settings);
        return $relative === '' ? '' : self::rootUrl($relative);
    }

    public static function report(string $scope, \Throwable $e): void
    {
        try {
            Log::write('system', $scope, 'error', '', $e->getMessage(), [
                'class' => get_class($e)
            ]);
        } catch (\Throwable $ignored) {
        }
    }

    private static function bool($value): string
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
    }

    private static function int($value, int $min, int $max, int $default): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private static function text($value, int $max): string
    {
        $value = trim(str_replace("\0", '', (string) $value));
        return Text::slice($value, $max);
    }

    private static function lines($value, int $maxLen, int $maxLines): string
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        $clean = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/\s+/', '', $line) ?? '';
            if ($line === '') {
                continue;
            }
            $clean[] = Text::slice($line, $maxLen);
            if (count($clean) >= $maxLines) {
                break;
            }
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    private static function paths($value, int $maxLines): string
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        $clean = [];

        foreach ($lines as $line) {
            $line = '/' . ltrim(trim((string) $line), '/');
            $line = preg_replace('#/+#', '/', $line) ?? '/';
            if ($line === '/' || strpos($line, '..') !== false) {
                continue;
            }
            $clean[] = Text::slice($line, 255);
            if (count($clean) >= $maxLines) {
                break;
            }
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    private static function urls($value, int $maxLines): string
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        $clean = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || !preg_match('#^https?://#i', $line)) {
                continue;
            }
            $clean[] = Text::slice($line, 1024);
            if (count($clean) >= $maxLines) {
                break;
            }
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    private static function urlOrRelative($value, int $max): string
    {
        $value = self::text($value, $max);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $value) || str_starts_with($value, '/')) {
            return $value;
        }

        return '';
    }

    private static function token($value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $value) ?? '';
        return Text::slice($value, 128);
    }

    private static function relativeFile($value): string
    {
        $value = trim(str_replace('\\', '/', (string) $value));
        $value = ltrim($value, '/');
        if ($value === '') {
            return '';
        }
        if (strpos($value, '..') !== false) {
            return '';
        }
        $value = preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $value) ?? '';
        return Text::slice($value, 255);
    }

    private static function priority($value, string $default): string
    {
        $num = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($num === false) {
            $num = (float) $default;
        }
        $num = max(0.1, min(1.0, (float) $num));
        return number_format($num, 1, '.', '');
    }

    private static function freq($value, string $default): string
    {
        $value = strtolower(trim((string) $value));
        $allowed = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
