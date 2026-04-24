<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

use Typecho\Cache;
use Typecho\Db;
use Typecho\Router;
use Typecho\Router\ParamsDelegateInterface;
use Utils\Helper;
use Widget\Contents\Page\Rows as PageRows;
use Widget\Metas\Category\Rows as CategoryRows;
use Widget\Metas\Tag\Cloud as TagCloud;
use Widget\Users\Author as AuthorWidget;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Files
{
    private static function cleanupTempFile(string $path): void
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            unlink($path);
        } finally {
            restore_error_handler();
        }
    }

    private static function restoreBackupFile(string $from, string $to): void
    {
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            rename($from, $to);
        } finally {
            restore_error_handler();
        }
    }

    private const STATE_KEY = 'renewseo:sync:state:v1';
    private const STATE_OPTION = 'renewSeoSyncState';

    private static array $runtimeState = [
        'pendingAt' => 0,
        'lastSyncAt' => 0,
        'managed' => [],
    ];

    public static function sync(string $reason = 'manual', bool $force = false, bool $writeInfoLog = true): array
    {
        $settings = Settings::load();

        if (($settings['enabled'] ?? '1') !== '1') {
            self::removeGenerated($settings);
            self::saveState([
                'pendingAt' => 0,
                'lastSyncAt' => time(),
                'managed' => [],
            ], $settings);
            return [
                'ok' => true,
                'disabled' => true,
                'files' => self::status($settings),
            ];
        }

        if (!$force && !self::shouldSyncNow($settings)) {
            self::markPendingSync();
            return [
                'ok' => true,
                'skipped' => true,
                'pending' => true,
                'files' => self::status($settings),
            ];
        }

        try {
            $desired = [];
            $tracked = self::trackedFiles($settings);

            if (($settings['robotsEnable'] ?? '1') === '1') {
                $desired['robots.txt'] = self::buildRobots($settings);
            }

            foreach (self::buildSitemapFiles($settings) as $name => $content) {
                $desired[$name] = $content;
            }

            $keyPath = Settings::keyRelativePath($settings);
            if (($settings['indexNowEnable'] ?? '0') === '1' && $keyPath !== '') {
                $desired[$keyPath] = (string) ($settings['indexNowKey'] ?? '');
            }

            foreach ($desired as $relative => $content) {
                self::writeFile($relative, $content);
            }

            self::removeUnexpectedGenerated($tracked, array_keys($desired));
            self::saveState([
                'pendingAt' => 0,
                'lastSyncAt' => time(),
                'managed' => array_values(array_keys($desired)),
            ], $settings);

            if ($writeInfoLog) {
                Log::write('file', 'sync', 'info', '', 'SEO 文件同步完成', [
                    'reason' => $reason,
                    'files' => array_values(array_keys($desired)),
                ]);
            }

            return [
                'ok' => true,
                'files' => self::status($settings),
            ];
        } catch (\Throwable $e) {
            Settings::report('files.sync', $e);
            Log::write('file', 'sync', 'error', '', $e->getMessage(), ['reason' => $reason]);
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'files' => self::status($settings),
            ];
        }
    }

    public static function syncIfNeeded(string $reason = 'runtime'): void
    {
        if (self::shouldSyncNow(Settings::load())) {
            self::sync($reason, false, false);
        }
    }

    public static function shouldSyncNow(array $settings): bool
    {
        if (($settings['enabled'] ?? '1') !== '1') {
            return false;
        }

        $debounce = max(0, (int) ($settings['sitemapDebounce'] ?? 0));
        if ($debounce === 0) {
            return true;
        }

        $state = self::state();
        $now = time();

        if (!empty($state['pendingAt'])) {
            return ($now - (int) $state['pendingAt']) >= $debounce;
        }

        return ($now - (int) ($state['lastSyncAt'] ?? 0)) >= $debounce;
    }

    public static function markPendingSync(): void
    {
        $settings = Settings::load();
        $state = self::state();
        if (empty($state['pendingAt'])) {
            $state['pendingAt'] = time();
        }
        self::saveState($state, $settings);
    }

    public static function buildRobots(array $settings): string
    {
        $blocked = self::normalizeLines((string) ($settings['robotsBlocked'] ?? ''));
        $allowedBots = self::normalizeLines((string) ($settings['robotsAllowed'] ?? ''));
        $deniedBots = self::normalizeLines((string) ($settings['robotsDenied'] ?? ''));
        $defaultAllow = ($settings['robotsDefault'] ?? 'allow') === 'allow';
        $mode = (string) ($settings['robotsMode'] ?? 'default_only');

        $groups = [];
        $groups[] = self::renderRobotsGroup(['*'], $defaultAllow, $blocked);

        if ($mode === 'all' || !$defaultAllow) {
            if (!empty($allowedBots)) {
                $groups[] = self::renderRobotsGroup($allowedBots, true, $blocked);
            }
        }

        if ($mode === 'all' || $defaultAllow) {
            if (!empty($deniedBots)) {
                $groups[] = self::renderRobotsGroup($deniedBots, false, []);
            }
        }

        $extra = trim((string) ($settings['robotsExtra'] ?? ''));
        if ($extra !== '') {
            $groups[] = $extra;
        }

        if (($settings['robotsSitemap'] ?? '1') === '1' && ($settings['sitemapEnable'] ?? '1') === '1') {
            $groups[] = 'Sitemap: ' . Settings::rootUrl('sitemap.xml');
        }

        foreach (self::normalizeLines((string) ($settings['robotsCustomSitemaps'] ?? '')) as $url) {
            $groups[] = 'Sitemap: ' . Settings::absoluteUrl($url);
        }

        return trim(implode("\n\n", array_filter($groups))) . "\n";
    }

    public static function status(array $settings): array
    {
        $files = [];
        $managed = array_values(array_filter(array_unique(array_merge(
            self::expectedFiles($settings),
            self::trackedFiles($settings)
        ))));
        foreach ($managed as $relative) {
            $path = Settings::rootPath($relative);
            $exists = is_file($path);
            $files[] = [
                'name' => $relative,
                'exists' => $exists,
                'mtime' => $exists ? ((int) @filemtime($path) ?: 0) : 0,
                'size' => $exists ? ((int) @filesize($path) ?: 0) : 0,
                'url' => $exists ? Settings::rootUrl($relative) : '',
            ];
        }

        usort($files, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));
        return $files;
    }

    public static function contentItem(int $cid): ?array
    {
        if ($cid <= 0) {
            return null;
        }

        try {
            $db = Db::get();
            $row = $db->fetchRow(
                $db->select('cid', 'slug', 'created', 'modified', 'type', 'status', 'authorId')
                    ->from('table.contents')
                    ->where('cid = ?', $cid)
                    ->limit(1)
            );

            if (!$row || !in_array((string) ($row['type'] ?? ''), ['post', 'page', 'attachment'], true)) {
                return null;
            }

            $url = self::contentUrl($db, $row);
            if ($url === '') {
                return null;
            }

            return [
                'cid' => (int) $row['cid'],
                'url' => $url,
                'created' => (int) ($row['created'] ?? 0),
                'modified' => (int) ($row['modified'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'type' => (string) ($row['type'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Settings::report('files.contentItem', $e);
            return null;
        }
    }

    public static function cleanupTransition(array $old, array $new): void
    {
        if (($old['indexNowKey'] ?? '') !== ($new['indexNowKey'] ?? '')) {
            self::removeFile(Settings::keyRelativePath($old));
        }

        if (($new['enabled'] ?? '1') !== '1') {
            self::removeGenerated($old);
        }
    }

    public static function removeGenerated(?array $settings = null): void
    {
        $settings = $settings ?? Settings::load();
        foreach (self::trackedFiles($settings, true) as $relative) {
            self::removeFile($relative);
        }

        self::saveState([
            'pendingAt' => 0,
            'lastSyncAt' => (int) (self::state()['lastSyncAt'] ?? 0),
            'managed' => [],
        ], $settings);
    }

    private static function buildSitemapFiles(array $settings): array
    {
        if (($settings['sitemapEnable'] ?? '1') !== '1') {
            return [];
        }

        $entries = self::sitemapEntries($settings);
        $limit = max(100, min(50000, (int) ($settings['sitemapSplit'] ?? 1000)));
        $chunks = array_chunk($entries, $limit);
        $files = [];

        if (empty($chunks)) {
            $chunks = [[]];
        }

        if (count($chunks) === 1) {
            $files['sitemap.xml'] = self::renderUrlSet($chunks[0]);
        } else {
            $index = [];
            foreach ($chunks as $offset => $chunk) {
                $name = 'sitemap-' . ($offset + 1) . '.xml';
                $files[$name] = self::renderUrlSet($chunk);
                $index[] = [
                    'loc' => Settings::rootUrl($name),
                    'lastmod' => self::chunkLastmod($chunk),
                ];
            }
            $files['sitemap.xml'] = self::renderSitemapIndex($index);
        }

        if (($settings['sitemapTxt'] ?? '1') === '1') {
            $files['sitemap.txt'] = self::renderTextSitemap($entries);
        }

        return $files;
    }

    private static function sitemapEntries(array $settings): array
    {
        $entries = [[
            'loc' => Settings::siteUrl(),
            'lastmod' => self::iso8601((int) (Helper::options()->time ?? time())),
            'changefreq' => (string) ($settings['sitemapFreqHome'] ?? 'daily'),
            'priority' => (string) ($settings['sitemapPriorityHome'] ?? '1.0'),
        ]];

        if (($settings['sitemapPost'] ?? '1') === '1') {
            foreach (self::postEntries($settings) as $entry) {
                $entries[] = $entry;
            }
        }

        if (($settings['sitemapPage'] ?? '1') === '1') {
            $pages = PageRows::allocWithAlias('renewseo-pages', null, null, false)
                ->toArray(['permalink', 'modified', 'created']);
            foreach ($pages as $row) {
                $entries[] = self::entry(
                    (string) ($row['permalink'] ?? ''),
                    (int) ($row['modified'] ?? $row['created'] ?? 0),
                    (string) ($settings['sitemapFreqPage'] ?? 'monthly'),
                    (string) ($settings['sitemapPriorityPage'] ?? '0.7')
                );
            }
        }

        if (($settings['sitemapCategory'] ?? '1') === '1') {
            $categories = CategoryRows::allocWithAlias('renewseo-categories', null, null, false)
                ->toArray(['permalink']);
            foreach ($categories as $row) {
                $entries[] = self::entry(
                    (string) ($row['permalink'] ?? ''),
                    0,
                    (string) ($settings['sitemapFreqTaxonomy'] ?? 'daily'),
                    (string) ($settings['sitemapPriorityCategory'] ?? '0.6')
                );
            }
        }

        if (($settings['sitemapTag'] ?? '0') === '1') {
            $tags = TagCloud::allocWithAlias('renewseo-tags', ['limit' => 0, 'ignoreZeroCount' => true], null, false)
                ->toArray(['permalink']);
            foreach ($tags as $row) {
                $entries[] = self::entry(
                    (string) ($row['permalink'] ?? ''),
                    0,
                    (string) ($settings['sitemapFreqTaxonomy'] ?? 'daily'),
                    (string) ($settings['sitemapPriorityTag'] ?? '0.5')
                );
            }
        }

        if (($settings['sitemapAuthor'] ?? '0') === '1') {
            foreach (self::authorEntries() as $entry) {
                $entries[] = $entry;
            }
        }

        $unique = [];
        foreach ($entries as $entry) {
            if (!empty($entry['loc'])) {
                $unique[$entry['loc']] = $entry;
            }
        }

        return array_values($unique);
    }

    private static function authorEntries(): array
    {
        try {
            $db = Db::get();
            $rows = $db->fetchAll(
                $db->select('table.users.uid')
                    ->from('table.users')
                    ->join('table.contents', 'table.contents.authorId = table.users.uid')
                    ->where('table.contents.status = ?', 'publish')
                    ->group('table.users.uid')
            );
        } catch (\Throwable $e) {
            Settings::report('files.authors', $e);
            return [];
        }

        $entries = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['uid'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $author = AuthorWidget::allocWithAlias('renewseo-author-' . $uid, ['uid' => $uid], null, false);
            if (method_exists($author, 'have') && !$author->have()) {
                continue;
            }
            $entries[] = self::entry((string) ($author->permalink ?? ''), 0, 'daily', '0.4');
        }

        return $entries;
    }

    private static function contentUrl(Db $db, array $row): string
    {
        $created = (int) ($row['created'] ?? 0);
        $slug = (string) ($row['slug'] ?? '');
        $type = (string) ($row['type'] ?? '');
        $cid = (int) ($row['cid'] ?? 0);
        $category = self::firstCategory($db, $cid);
        $directory = $category ? self::categoryDirectory($db, (int) $category['mid']) : [];

        $delegate = new class ($cid, $slug, $created, $category['slug'] ?? '', $directory) implements ParamsDelegateInterface {
            public function __construct(
                private int $cid,
                private string $slug,
                private int $created,
                private string $category,
                private array $directory
            ) {
            }

            public function getRouterParam(string $key): string
            {
                switch ($key) {
                    case 'cid':
                        return (string) $this->cid;
                    case 'slug':
                        return urlencode($this->slug);
                    case 'category':
                        return urlencode($this->category);
                    case 'directory':
                        return implode('/', array_map('urlencode', $this->directory));
                    case 'year':
                        return date('Y', $this->created);
                    case 'month':
                        return date('m', $this->created);
                    case 'day':
                        return date('d', $this->created);
                    default:
                        return '{' . $key . '}';
                }
            }
        };

        return Router::url($type, $delegate, Helper::options()->index);
    }

    private static function firstCategory(Db $db, int $cid): ?array
    {
        try {
            $row = $db->fetchRow(
                $db->select('table.metas.mid', 'table.metas.slug', 'table.metas.parent')
                    ->from('table.metas')
                    ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid = ?', $cid)
                    ->where('table.metas.type = ?', 'category')
                    ->order('table.metas.order', Db::SORT_ASC)
                    ->limit(1)
            );
            return $row ?: null;
        } catch (\Throwable $e) {
            Settings::report('files.firstCategory', $e);
            return null;
        }
    }

    private static function categoryDirectory(Db $db, int $mid): array
    {
        $directory = [];
        $seen = [];

        while ($mid > 0 && !isset($seen[$mid])) {
            $seen[$mid] = true;
            $row = $db->fetchRow(
                $db->select('mid', 'slug', 'parent')
                    ->from('table.metas')
                    ->where('mid = ?', $mid)
                    ->limit(1)
            );

            if (!$row) {
                break;
            }

            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '') {
                array_unshift($directory, $slug);
            }
            $mid = (int) ($row['parent'] ?? 0);
        }

        return $directory;
    }

    private static function writeFile(string $relative, string $content): void
    {
        $path = Settings::rootPath($relative);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new \RuntimeException('目标目录不存在：' . $relative);
        }

        $tempPath = $directory . DIRECTORY_SEPARATOR . '.' . basename($path) . '.tmp.' . bin2hex(random_bytes(6));
        $backupPath = $directory . DIRECTORY_SEPARATOR . '.' . basename($path) . '.bak.' . bin2hex(random_bytes(6));
        $backupCreated = false;
        $written = file_put_contents($tempPath, $content, LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('无法写入文件：' . $relative);
        }

        try {
            if (is_file($path)) {
                if (!rename($path, $backupPath)) {
                    throw new \RuntimeException('无法替换文件：' . $relative);
                }
                $backupCreated = true;
            }

            if (!rename($tempPath, $path)) {
                if ($backupCreated && is_file($backupPath)) {
                    rename($backupPath, $path);
                }
                throw new \RuntimeException('无法写入文件：' . $relative);
            }

            if ($backupCreated && is_file($backupPath) && !unlink($backupPath) && is_file($backupPath)) {
                throw new \RuntimeException('无法清理旧文件备份：' . $relative);
            }
        } finally {
            if (is_file($tempPath)) {
                self::cleanupTempFile($tempPath);
            }
            if (is_file($backupPath) && !is_file($path)) {
                self::restoreBackupFile($backupPath, $path);
            }
        }
    }

    private static function removeUnexpectedGenerated(array $tracked, array $keep): void
    {
        $keepMap = array_fill_keys($keep, true);
        foreach ($tracked as $relative) {
            if (!isset($keepMap[$relative])) {
                self::removeFile($relative);
            }
        }
    }

    private static function removeFile(string $relative): void
    {
        if ($relative === '') {
            return;
        }

        $path = Settings::rootPath($relative);
        if (!is_file($path)) {
            return;
        }

        $error = null;
        set_error_handler(static function (int $_severity, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });

        try {
            $deleted = unlink($path);
        } finally {
            restore_error_handler();
        }

        if (!$deleted && is_file($path)) {
            throw new \RuntimeException($error ? '无法删除文件：' . $relative . '（' . $error . '）' : '无法删除文件：' . $relative);
        }
    }

    private static function state(): array
    {
        $cache = Cache::getInstance();
        if ($cache->enabled()) {
            try {
                $hit = false;
                $state = $cache->get(self::STATE_KEY, $hit);
                if ($hit && is_array($state)) {
                    self::$runtimeState = array_merge(self::$runtimeState, $state);
                }
            } catch (\Throwable $e) {
                Settings::report('files.state.get', $e);
            }
        }

        if ((int) (self::$runtimeState['lastSyncAt'] ?? 0) === 0 && (int) (self::$runtimeState['pendingAt'] ?? 0) === 0) {
            self::$runtimeState = array_merge(self::$runtimeState, self::loadStateOption());
        }

        return self::$runtimeState;
    }

    private static function saveState(array $state, array $settings): void
    {
        self::$runtimeState = array_merge(self::$runtimeState, $state);
        $cache = Cache::getInstance();
        if ($cache->enabled()) {
            try {
                $ttl = max(600, max(0, (int) ($settings['sitemapDebounce'] ?? 0)) * 20);
                $cache->set(self::STATE_KEY, self::$runtimeState, $ttl);
            } catch (\Throwable $e) {
                Settings::report('files.state.set', $e);
            }
        }

        self::storeStateOption(self::$runtimeState);
    }

    private static function loadStateOption(): array
    {
        try {
            $db = Db::get();
            $row = $db->fetchRow(
                $db->select('value')
                    ->from('table.options')
                    ->where('name = ?', self::STATE_OPTION)
                    ->where('user = ?', 0)
                    ->limit(1)
            );
        } catch (\Throwable $e) {
            Settings::report('files.state.option.read', $e);
            return [];
        }

        $decoded = json_decode((string) ($row['value'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function storeStateOption(array $state): void
    {
        try {
            $db = Db::get();
            $value = json_encode([
                'pendingAt' => (int) ($state['pendingAt'] ?? 0),
                'lastSyncAt' => (int) ($state['lastSyncAt'] ?? 0),
                'managed' => array_values(array_filter(array_map(
                    static fn($item): string => is_string($item) ? trim($item) : '',
                    (array) ($state['managed'] ?? [])
                ))),
            ]);

            $exists = $db->fetchRow(
                $db->select('name')
                    ->from('table.options')
                    ->where('name = ?', self::STATE_OPTION)
                    ->where('user = ?', 0)
                    ->limit(1)
            );

            if ($exists) {
                $db->query(
                    $db->update('table.options')->rows(['value' => $value])
                        ->where('name = ?', self::STATE_OPTION)
                        ->where('user = ?', 0)
                );
            } else {
                $db->query(
                    $db->insert('table.options')->rows([
                        'name' => self::STATE_OPTION,
                        'value' => $value,
                        'user' => 0,
                    ])
                );
            }
        } catch (\Throwable $e) {
            Settings::report('files.state.option.write', $e);
        }
    }

    private static function renderRobotsGroup(array $agents, bool $allow, array $blocked): string
    {
        $lines = [];
        foreach ($agents as $agent) {
            $lines[] = 'User-agent: ' . $agent;
        }
        $lines[] = $allow ? 'Allow: /' : 'Disallow: /';
        if ($allow) {
            foreach ($blocked as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
        }
        return implode("\n", $lines);
    }

    private static function normalizeLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }
        return array_values(array_unique($result));
    }

    private static function expectedFiles(array $settings): array
    {
        return array_values(array_filter([
            'robots.txt',
            'sitemap.xml',
            'sitemap.txt',
            Settings::keyRelativePath($settings),
        ]));
    }

    private static function trackedFiles(array $settings, bool $includeLegacyFallback = false): array
    {
        $state = self::state();
        $tracked = array_map(
            static fn($item): string => is_string($item) ? trim($item) : '',
            (array) ($state['managed'] ?? [])
        );

        if ($includeLegacyFallback && !array_key_exists('managed', $state)) {
            foreach (self::legacyTrackedFiles($settings) as $relative) {
                $tracked[] = $relative;
            }
        }

        return array_values(array_filter(array_unique($tracked)));
    }

    private static function legacyTrackedFiles(array $settings): array
    {
        $files = self::expectedFiles($settings);
        foreach (glob(Settings::rootPath('sitemap-*.xml')) ?: [] as $path) {
            if (is_file($path)) {
                $files[] = basename($path);
            }
        }

        return array_values(array_filter(array_unique($files)));
    }

    private static function postEntries(array $settings): array
    {
        $entries = [];
        $db = Db::get();
        $cursor = 0;
        $limit = 1000;

        do {
            $rows = $db->fetchAll(
                $db->select('cid', 'slug', 'created', 'modified', 'type', 'status', 'authorId')
                    ->from('table.contents')
                    ->where('status = ?', 'publish')
                    ->where('created < ?', Helper::options()->time)
                    ->where('type = ?', 'post')
                    ->where('cid > ?', $cursor)
                    ->order('cid', Db::SORT_ASC)
                    ->limit($limit)
            );

            foreach ($rows as $row) {
                $cursor = max($cursor, (int) ($row['cid'] ?? 0));
                $url = self::contentUrl($db, $row);
                if ($url === '') {
                    continue;
                }

                $entries[] = self::entry(
                    $url,
                    (int) ($row['modified'] ?? $row['created'] ?? 0),
                    (string) ($settings['sitemapFreqPost'] ?? 'weekly'),
                    (string) ($settings['sitemapPriorityPost'] ?? '0.8')
                );
            }
        } while (count($rows) === $limit);

        return $entries;
    }

    private static function renderUrlSet(array $entries): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . self::xml((string) $entry['loc']) . '</loc>';
            if (!empty($entry['lastmod'])) {
                $lines[] = '    <lastmod>' . self::xml((string) $entry['lastmod']) . '</lastmod>';
            }
            if (!empty($entry['changefreq'])) {
                $lines[] = '    <changefreq>' . self::xml((string) $entry['changefreq']) . '</changefreq>';
            }
            if (!empty($entry['priority'])) {
                $lines[] = '    <priority>' . self::xml((string) $entry['priority']) . '</priority>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        return implode("\n", $lines) . "\n";
    }

    private static function renderSitemapIndex(array $entries): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $lines[] = '  <sitemap>';
            $lines[] = '    <loc>' . self::xml((string) $entry['loc']) . '</loc>';
            if (!empty($entry['lastmod'])) {
                $lines[] = '    <lastmod>' . self::xml((string) $entry['lastmod']) . '</lastmod>';
            }
            $lines[] = '  </sitemap>';
        }

        $lines[] = '</sitemapindex>';
        return implode("\n", $lines) . "\n";
    }

    private static function renderTextSitemap(array $entries): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            if (!empty($entry['loc'])) {
                $lines[] = (string) $entry['loc'];
            }
        }
        return implode("\n", $lines) . "\n";
    }

    private static function entry(string $loc, int $modified, string $changefreq, string $priority): array
    {
        return [
            'loc' => Settings::absoluteUrl($loc),
            'lastmod' => self::iso8601($modified),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    private static function chunkLastmod(array $chunk): string
    {
        $timestamps = [];
        foreach ($chunk as $entry) {
            $timestamps[] = strtotime((string) ($entry['lastmod'] ?? '')) ?: 0;
        }
        return self::iso8601(max($timestamps) ?: time());
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function iso8601(int $timestamp): string
    {
        $timestamp = $timestamp > 0 ? $timestamp : time();
        return gmdate('c', $timestamp);
    }
}
