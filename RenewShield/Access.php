<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Access
{
    public static function summary(array $settings): array
    {
        $parsed = self::parsed($settings);
        $rules = (array) ($parsed['rules'] ?? []);
        $issues = (array) ($parsed['issues'] ?? []);

        return [
            'ruleCount' => count($rules),
            'issueCount' => count($issues),
            'rules' => array_slice(array_map([self::class, 'describeRule'], $rules), 0, 6),
            'issues' => array_slice($issues, 0, 6),
        ];
    }

    public static function matchPath(Context $context, array $settings): ?array
    {
        foreach (self::rules($settings) as $rule) {
            if (($rule['kind'] ?? '') !== 'path') {
                continue;
            }
            if (!self::pathMatches($context->path, (string) $rule['value'])) {
                continue;
            }
            if (self::authorized((string) $rule['need'])) {
                return null;
            }
            return self::decision($rule, $settings);
        }

        return null;
    }

    public static function matchArchive(object $archive, array $settings): ?array
    {
        $cid = (int) ($archive->cid ?? 0);
        $slug = strtolower((string) ($archive->slug ?? $archive->archiveSlug ?? ''));
        $type = strtolower((string) ($archive->archiveType ?? ''));
        $categories = self::metaSlugs((array) ($archive->categories ?? []));
        $tags = self::metaSlugs((array) ($archive->tags ?? []));

        foreach (self::rules($settings) as $rule) {
            $matched = match ($rule['kind'] ?? '') {
                'cid' => $cid > 0 && $cid === (int) $rule['value'],
                'slug' => $slug !== '' && $slug === strtolower((string) $rule['value']),
                'type' => $type !== '' && $type === strtolower((string) $rule['value']),
                'category' => in_array(strtolower((string) $rule['value']), $categories, true)
                    || ($type === 'category' && $slug === strtolower((string) $rule['value'])),
                'tag' => in_array(strtolower((string) $rule['value']), $tags, true)
                    || ($type === 'tag' && $slug === strtolower((string) $rule['value'])),
                default => false,
            };

            if (!$matched) {
                continue;
            }

            if (self::authorized((string) $rule['need'])) {
                return null;
            }

            return self::decision($rule, $settings);
        }

        return null;
    }

    public static function issues(array $settings): array
    {
        return self::parsed($settings)['issues'];
    }

    private static function decision(array $rule, array $settings): array
    {
        $action = (string) ($rule['action'] ?? 'html');
        if ($action !== 'redirect' && $action !== '403') {
            $action = 'html';
        }

        return [
            'action' => $action,
            'need' => (string) ($rule['need'] ?? 'login'),
            'redirect' => $action === 'redirect'
                ? Settings::normalize([
                    'accessRedirect' => (string) ($rule['extra'] ?? '') !== '' ? (string) ($rule['extra'] ?? '') : (string) ($settings['accessRedirect'] ?? ''),
                ])['accessRedirect']
                : '',
            'html' => (string) ($settings['accessHtml'] ?? ''),
        ];
    }

    private static function authorized(string $need): bool
    {
        $need = strtolower(trim($need));
        $user = \Widget\User::alloc();

        if ($need === '' || $need === 'login') {
            return $user->hasLogin();
        }

        if (!str_starts_with($need, 'role:')) {
            return $user->hasLogin();
        }

        $roles = preg_split('/[|,]/', trim(substr($need, 5))) ?: [];
        foreach ($roles as $role) {
            $role = trim($role);
            if ($role !== '' && $user->pass($role, true)) {
                return true;
            }
        }

        return false;
    }

    private static function rules(array $settings): array
    {
        return self::parsed($settings)['rules'];
    }

    private static function parsed(array $settings): array
    {
        static $cache = [];
        $source = (string) ($settings['accessRules'] ?? '');
        $hash = sha1($source);
        if (isset($cache[$hash])) {
            return $cache[$hash];
        }

        $rules = [];
        $issues = [];
        foreach (Text::lines($source, 255, 200) as $index => $line) {
            $lineNo = $index + 1;
            $parts = array_map('trim', explode('=>', $line));
            if (count($parts) < 2) {
                $issues[] = '第 ' . $lineNo . ' 行规则格式无效：' . $line;
                continue;
            }

            [$match, $need] = $parts;
            $action = $parts[2] ?? 'html';
            $extra = '';
            if (str_starts_with($action, 'redirect:')) {
                $extra = trim(substr($action, 9));
                $action = 'redirect';
            }

            $kind = 'path';
            $value = $match;
            if (strpos($match, ':') !== false) {
                [$kind, $value] = array_map('trim', explode(':', $match, 2));
                $kind = strtolower($kind);
            }

            if (!in_array($kind, ['path', 'cid', 'slug', 'type', 'category', 'tag'], true)) {
                $issues[] = '第 ' . $lineNo . ' 行匹配对象无效：' . $line;
                continue;
            }

            if (!self::validMatch($kind, $value)) {
                $issues[] = '第 ' . $lineNo . ' 行匹配值无效：' . $line;
                continue;
            }

            $need = strtolower($need);
            if (!self::validNeed($need)) {
                $issues[] = '第 ' . $lineNo . ' 行权限写法无效：' . $line;
                continue;
            }

            $action = strtolower($action);
            if (!in_array($action, ['html', '403', 'redirect'], true)) {
                $issues[] = '第 ' . $lineNo . ' 行处理方式无效：' . $line;
                continue;
            }

            if ($action === 'redirect') {
                $rawTarget = $extra !== '' ? $extra : (string) ($settings['accessRedirect'] ?? '');
                $target = (string) Settings::normalize(['accessRedirect' => $rawTarget])['accessRedirect'];
                if ($rawTarget === '') {
                    $issues[] = '第 ' . $lineNo . ' 行缺少跳转地址：' . $line;
                    continue;
                }
                if ($target === '') {
                    $issues[] = '第 ' . $lineNo . ' 行跳转地址无效：' . $line;
                    continue;
                }
                $extra = $target;
            }

            $rules[] = [
                'raw' => $line,
                'kind' => $kind,
                'value' => $value,
                'need' => $need,
                'action' => $action,
                'extra' => $extra,
                'line' => $lineNo,
            ];
        }

        self::reportIssues($hash, $issues);

        return $cache[$hash] = [
            'rules' => $rules,
            'issues' => $issues,
        ];
    }

    private static function pathMatches(string $path, string $rule): bool
    {
        $rule = Settings::normalizePath($rule);
        if (str_ends_with($rule, '*')) {
            return str_starts_with($path, rtrim($rule, '*'));
        }

        return rtrim($path, '/') === rtrim($rule, '/');
    }

    private static function validNeed(string $need): bool
    {
        if ($need === '' || $need === 'login') {
            return true;
        }

        return preg_match('/^role:[a-z0-9_]+(?:[|,][a-z0-9_]+)*$/', $need) === 1;
    }

    private static function validMatch(string $kind, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return match ($kind) {
            'path' => str_starts_with($value, '/'),
            'cid' => ctype_digit($value) && (int) $value > 0,
            'type' => preg_match('/^(post|page|category|tag|author|search|index|date|attachment)$/', strtolower($value)) === 1,
            default => preg_match('/^[a-z0-9._\-\/]+$/i', $value) === 1,
        };
    }

    private static function describeRule(array $rule): array
    {
        $kind = (string) ($rule['kind'] ?? 'path');
        $value = (string) ($rule['value'] ?? '');
        $need = (string) ($rule['need'] ?? 'login');
        $action = (string) ($rule['action'] ?? 'html');
        $extra = (string) ($rule['extra'] ?? '');

        $target = match ($kind) {
            'path' => '路径 ' . $value,
            'cid' => '内容 ID ' . $value,
            'slug' => 'Slug ' . $value,
            'type' => '归档类型 ' . $value,
            'category' => '分类 ' . $value,
            'tag' => '标签 ' . $value,
            default => $kind . ':' . $value,
        };

        $needText = $need === 'login'
            ? '登录用户'
            : '角色 ' . str_replace(['role:', '|', ','], ['', ' / ', ' / '], $need);

        $actionText = match ($action) {
            '403' => '返回 403',
            'redirect' => '跳转到 ' . ($extra !== '' ? $extra : '默认地址'),
            default => '显示自定义 HTML',
        };

        return [
            'line' => (int) ($rule['line'] ?? 0),
            'target' => $target,
            'need' => $needText,
            'action' => $actionText,
            'summary' => $target . ' -> ' . $needText . ' -> ' . $actionText,
        ];
    }

    private static function metaSlugs(array $items): array
    {
        $slugs = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug = strtolower(trim((string) ($item['slug'] ?? '')));
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    private static function reportIssues(string $hash, array $issues): void
    {
        if ($issues === []) {
            return;
        }

        $stateKey = 'access:issues:' . $hash;
        if (State::get($stateKey, null) !== null) {
            return;
        }

        State::set($stateKey, 1, 3600);
        Log::write('access', 'verify', 'observe', 'access.rule.invalid', 0, '检测到无效访问规则', [
            'issues' => $issues,
        ]);
    }
}
