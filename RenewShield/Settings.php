<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Typecho\Cache;
use Typecho\Common;
use Typecho\Plugin\Exception as PluginException;
use Utils\Helper;
use Utils\Pref;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Settings
{
    private const NAME = 'RenewShield';
    private const CACHE_KEY = 'renewshield:settings:v2';

    private static ?array $runtime = null;

    public static function load(): array
    {
        return Pref::load(
            self::$runtime,
            self::CACHE_KEY,
            self::defaults(),
            static fn(): array => self::readStored('load.read'),
            [self::class, 'normalize'],
            [self::class, 'ensureStored'],
            [self::class, 'report']
        );
    }

    public static function loadFresh(): array
    {
        $raw = self::readStored('fresh.read');
        if (empty($raw)) {
            self::ensureStored();
            $raw = self::readStored('fresh.retry');
        }

        return self::normalize(array_merge(self::defaults(), $raw));
    }

    public static function defaults(): array
    {
        return [
            'enabled' => '1',
            'profile' => 'balanced',
            'cacheTtl' => 300,
            'panelSize' => 10,
            'logKeepDays' => 30,
            'signKey' => '',
            'wafMode' => 'balanced',
            'riskMode' => 'challenge',
            'allowSpiders' => '1',
            'spiderCacheHours' => 24,
            'denyEmptyUa' => '1',
            'blockScriptUa' => '1',
            'browserCheck' => '0',
            'minChrome' => 90,
            'minFirefox' => 90,
            'minEdge' => 90,
            'minSafari' => 13,
            'secFetchCheck' => '0',
            'headerCompleteness' => '0',
            'httpVersionCheck' => '0',
            'blockProxy' => '0',
            'denyBadMethods' => '1',
            'wafEnable' => '1',
            'autoBanHours' => 24,
            'generalWindow' => 60,
            'generalLimit' => 150,
            'loginWindow' => 900,
            'loginLimit' => 5,
            'commentWindow' => 300,
            'commentLimit' => 6,
            'xmlrpcWindow' => 600,
            'xmlrpcLimit' => 10,
            'badLimit' => 8,
            'challengeWait' => 3,
            'trapBanHours' => 72,
            'commentLinks' => 3,
            'commentMinSeconds' => 4,
            'commentRequireChallenge' => '0',
            'uploadDoubleExt' => '1',
            'uploadScan' => '1',
            'uploadMaxKb' => 0,
            'xmlrpcAllowlist' => '',
            'proxyTrusted' => '',
            'ipAllowlist' => "127.0.0.1\n::1",
            'ipDenylist' => '',
            'uaAllowlist' => '',
            'uaDenylist' => '',
            'trapPaths' => implode("\n", [
                '/.env',
                '/.user.ini',
                '/.git/',
                '/.svn/',
                '/phpmyadmin/',
                '/phpmyadmin/index.php',
                '/pma/',
                '/install/',
                '/install.php',
                '/adminer.php',
                '/mysql.php',
                '/composer.json',
                '/composer.lock',
                '/.git/HEAD',
                '/wp-admin/',
                '/wp-login.php',
                '/xmlrpc.php',
                '/vendor/phpunit/',
                '/vendor/autoload.php',
                '/vendor/composer/installed.json',
                '/server-status',
                '/phpinfo.php',
                '/info.php',
                '/install.php.bak',
                '/config.inc.php',
                '/config.inc.php.bak',
                '/config.php.bak',
                '/backup.zip',
                '/backup.sql',
                '/dump.sql',
                '/usr/uploads/*.php',
                '/usr/uploads/*.php5',
                '/usr/uploads/*.pht',
                '/usr/uploads/*.phtml',
                '/usr/uploads/*.phar',
                '/var/Utils/',
                '/var/IXR/',
                '/var/Widget/',
                '/var/Typecho/',
                '/runtime/logs/',
            ]),
            'accessRules' => '',
            'accessHtml' => '<p><strong>当前内容暂未开放</strong></p><p>请使用有权限的账号登录后访问。</p><p>该请求已被访问规则拦截，原页面未继续加载。</p>',
            'accessRedirect' => '',
        ];
    }

    public static function boolKeys(): array
    {
        return [
            'enabled',
            'allowSpiders',
            'denyEmptyUa',
            'blockScriptUa',
            'browserCheck',
            'secFetchCheck',
            'headerCompleteness',
            'httpVersionCheck',
            'blockProxy',
            'denyBadMethods',
            'wafEnable',
            'commentRequireChallenge',
            'uploadDoubleExt',
            'uploadScan',
        ];
    }

    public static function profiles(): array
    {
        return [
            'conservative' => '保守模式',
            'balanced' => '平衡模式',
            'strict' => '严格模式',
            'custom' => '自定义',
        ];
    }

    public static function tabs(): array
    {
        return ['global', 'request', 'challenge', 'access', 'ops'];
    }

    public static function wafModes(): array
    {
        return [
            'observe' => '仅观察',
            'balanced' => '平衡模式',
            'block' => '直接拦截',
        ];
    }

    public static function riskModes(): array
    {
        return [
            'observe' => '仅观察',
            'challenge' => '基础验证',
            'block' => '直接拦截',
        ];
    }

    public static function store(array $settings): void
    {
        $stored = array_merge(self::defaults(), self::readStored('store.read'));
        $settings = self::normalize(array_merge($stored, $settings));
        \Widget\Plugins\Edit::configPlugin(self::NAME, $settings);
        self::clear();
    }

    public static function storeProfile(array $settings): void
    {
        $stored = array_merge(self::defaults(), self::readStored('store.read'));
        $settings = self::applyProfile(array_merge($stored, $settings));
        $settings = self::normalize($settings);
        \Widget\Plugins\Edit::configPlugin(self::NAME, $settings);
        self::clear();
    }

    public static function ensureStored(): void
    {
        Pref::sync(
            self::NAME,
            self::defaults(),
            [self::class, 'normalize'],
            [self::class, 'report'],
            null,
            static fn(): array => self::readStored('ensure.read')
        );
        self::clear();
    }

    public static function clear(): void
    {
        Pref::forget(self::$runtime, self::CACHE_KEY, [self::class, 'report']);
    }

    public static function normalize(array $settings): array
    {
        $settings = array_intersect_key($settings, self::defaults());
        $settings = array_merge(self::defaults(), $settings);

        foreach (self::boolKeys() as $key) {
            $settings[$key] = self::bool($settings[$key] ?? '0');
        }

        $settings['profile'] = self::choice((string) ($settings['profile'] ?? 'balanced'), array_keys(self::profiles()), 'balanced');
        $settings['wafMode'] = self::choice((string) ($settings['wafMode'] ?? 'balanced'), array_keys(self::wafModes()), 'balanced');
        $settings['riskMode'] = self::choice((string) ($settings['riskMode'] ?? 'challenge'), array_keys(self::riskModes()), 'challenge');

        foreach ([
            'cacheTtl' => [60, 3600, 300],
            'panelSize' => [10, 200, 10],
            'logKeepDays' => [1, 3650, 30],
            'spiderCacheHours' => [1, 168, 24],
            'minChrome' => [0, 999, 90],
            'minFirefox' => [0, 999, 90],
            'minEdge' => [0, 999, 90],
            'minSafari' => [0, 999, 13],
            'autoBanHours' => [1, 720, 24],
            'generalWindow' => [10, 3600, 60],
            'generalLimit' => [5, 10000, 150],
            'loginWindow' => [60, 86400, 900],
            'loginLimit' => [2, 100, 5],
            'commentWindow' => [30, 86400, 300],
            'commentLimit' => [1, 100, 6],
            'xmlrpcWindow' => [30, 86400, 600],
            'xmlrpcLimit' => [1, 500, 10],
            'badLimit' => [1, 100, 8],
            'challengeWait' => [0, 30, 3],
            'trapBanHours' => [1, 720, 72],
            'commentLinks' => [0, 50, 3],
            'commentMinSeconds' => [0, 60, 4],
            'uploadMaxKb' => [0, 102400, 0],
        ] as $key => [$min, $max, $default]) {
            $settings[$key] = self::int($settings[$key] ?? $default, $min, $max, $default);
        }

        $settings['signKey'] = self::token($settings['signKey'] ?? '');
        if ($settings['signKey'] === '') {
            $settings['signKey'] = bin2hex(random_bytes(24));
        }

        $settings['ipAllowlist'] = self::ipLines($settings['ipAllowlist'] ?? '');
        $settings['ipDenylist'] = self::ipLines($settings['ipDenylist'] ?? '');
        $settings['xmlrpcAllowlist'] = self::ipLines($settings['xmlrpcAllowlist'] ?? '');
        $settings['proxyTrusted'] = self::ipLines($settings['proxyTrusted'] ?? '');
        $settings['uaAllowlist'] = self::textLines($settings['uaAllowlist'] ?? '', 255, 200);
        $settings['uaDenylist'] = self::textLines($settings['uaDenylist'] ?? '', 255, 200);
        $settings['trapPaths'] = self::pathLines($settings['trapPaths'] ?? '');
        $settings['accessRules'] = self::ruleLines($settings['accessRules'] ?? '');
        $settings['accessHtml'] = self::html($settings['accessHtml'] ?? '', 12000);
        $settings['accessRedirect'] = self::urlOrRelative($settings['accessRedirect'] ?? '', 1024);
        $settings['profile'] = self::profileTag($settings);

        return $settings;
    }

    public static function cache(): Cache
    {
        return Cache::getInstance();
    }

    public static function panelUrl(): string
    {
        return Helper::url(self::NAME . '/Panel.php');
    }

    public static function panelQueryUrl(array $query = []): string
    {
        $clean = [];
        foreach ($query as $key => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }

        if ($clean === []) {
            return self::panelUrl();
        }

        $url = self::panelUrl();
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($clean);
    }

    public static function assetUrl(string $path): string
    {
        return Common::url(self::NAME . '/' . ltrim($path, '/'), (string) Helper::options()->pluginUrl);
    }

    public static function actionUrl(string $do = '', bool $secure = false): string
    {
        $path = '/action/renew-shield';
        if ($do !== '') {
            $path .= '?do=' . rawurlencode($do);
        }

        if ($secure) {
            return \Widget\Security::alloc()->getIndex($path);
        }

        return Common::url($path, (string) Helper::options()->index);
    }

    public static function siteUrl(): string
    {
        return rtrim((string) Helper::options()->siteUrl, '/') . '/';
    }

    public static function profileDefaults(string $profile): array
    {
        return match ($profile) {
            'conservative' => [
                'wafMode' => 'observe',
                'riskMode' => 'challenge',
                'browserCheck' => '0',
                'secFetchCheck' => '0',
                'headerCompleteness' => '0',
                'httpVersionCheck' => '0',
                'blockProxy' => '0',
                'generalWindow' => 60,
                'generalLimit' => 180,
                'loginLimit' => 6,
                'commentLimit' => 8,
                'challengeWait' => 2,
                'badLimit' => 10,
                'commentRequireChallenge' => '0',
            ],
            'strict' => [
                'wafMode' => 'block',
                'riskMode' => 'block',
                'browserCheck' => '1',
                'secFetchCheck' => '1',
                'headerCompleteness' => '1',
                'httpVersionCheck' => '0',
                'blockProxy' => '1',
                'generalWindow' => 60,
                'generalLimit' => 90,
                'loginLimit' => 4,
                'commentLimit' => 5,
                'challengeWait' => 4,
                'badLimit' => 6,
                'commentRequireChallenge' => '1',
            ],
            'balanced' => [
                'wafMode' => 'balanced',
                'riskMode' => 'challenge',
                'browserCheck' => '0',
                'secFetchCheck' => '0',
                'headerCompleteness' => '0',
                'httpVersionCheck' => '0',
                'blockProxy' => '0',
                'generalWindow' => 60,
                'generalLimit' => 150,
                'loginLimit' => 5,
                'commentLimit' => 6,
                'challengeWait' => 3,
                'badLimit' => 8,
                'commentRequireChallenge' => '0',
            ],
            default => [],
        };
    }

    public static function rootPath(string $relative = ''): string
    {
        $root = rtrim((string) __TYPECHO_ROOT_DIR__, '\\/');
        if ($relative === '') {
            return $root;
        }

        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
        return $root . DIRECTORY_SEPARATOR . $relative;
    }

    public static function report(string $scope, \Throwable $e): void
    {
        try {
            Log::write('system', 'error', 'observe', 'settings.' . $scope, 0, $e->getMessage(), [
                'class' => get_class($e),
            ]);
        } catch (\Throwable) {
        }
    }

    private static function readStored(string $scope): array
    {
        try {
            return (array) Helper::options()->plugin(self::NAME)->toArray();
        } catch (PluginException) {
            return [];
        } catch (\Throwable $e) {
            self::report($scope, $e);
            return [];
        }
    }

    private static function applyProfile(array $settings): array
    {
        $profile = self::choice((string) ($settings['profile'] ?? 'balanced'), array_keys(self::profiles()), 'balanced');
        if ($profile === 'custom') {
            return $settings;
        }

        foreach (self::profileDefaults($profile) as $key => $value) {
            $settings[$key] = $value;
        }

        $settings['profile'] = $profile;
        return $settings;
    }

    private static function profileTag(array $settings): string
    {
        foreach (['conservative', 'balanced', 'strict'] as $profile) {
            $matched = true;
            foreach (self::profileDefaults($profile) as $key => $value) {
                if ((string) ($settings[$key] ?? '') !== (string) $value) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                return $profile;
            }
        }

        return 'custom';
    }

    private static function bool(mixed $value): string
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
    }

    private static function choice(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function int(mixed $value, int $min, int $max, int $default): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private static function token(mixed $value): string
    {
        $value = preg_replace('/[^a-f0-9]/i', '', (string) $value) ?? '';
        return Text::cut(strtolower($value), 96);
    }

    private static function textLines(string $value, int $maxLen, int $maxLines): string
    {
        return implode("\n", Text::lines($value, $maxLen, $maxLines));
    }

    private static function ipLines(string $value): string
    {
        $clean = [];
        foreach (Text::lines($value, 64, 200) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (filter_var($line, FILTER_VALIDATE_IP) === false
                && !preg_match('/^[0-9a-f:\.]+\/\d{1,3}$/i', $line)) {
                continue;
            }
            $clean[] = strtolower($line);
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    private static function pathLines(string $value): string
    {
        $clean = [];
        foreach (Text::lines($value, 255, 200) as $line) {
            $line = self::normalizePath($line);
            if ($line === '/' || str_contains($line, '..')) {
                continue;
            }
            $clean[] = Text::cut($line, 255);
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        return preg_replace('#/+#', '/', $path) ?? '/';
    }

    private static function ruleLines(string $value): string
    {
        return implode("\n", Text::lines($value, 255, 200));
    }

    private static function html(string $value, int $max): string
    {
        return Text::cut(trim((string) $value), $max);
    }

    private static function urlOrRelative(string $value, int $max): string
    {
        $value = Text::cut(trim($value), $max);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return preg_replace('#/+#', '/', $value) ?? '/';
        }

        return '';
    }
}
