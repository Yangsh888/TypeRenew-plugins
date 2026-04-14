<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

use Typecho\Common;
use Typecho\Db;
use Typecho\Router;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Files
{
    private static bool $checked = false;

    public static function sync(string $reason = 'manual', bool $force = false): array
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '1') !== '1') {
            self::removeGenerated($settings);
            self::storeStateStamp($settings);
            return $force ? ['ok' => true, 'disabled' => true] : ['ok' => false, 'message' => 'plugin disabled'];
        }

        $lockKey = 'renewseo:sync';
        $cache = Settings::cache();
        $locked = $cache->enabled() ? $cache->tryLock($lockKey, 30) : true;
        if (!$locked) {
            return ['ok' => false, 'message' => 'locked'];
        }

        try {
            $result = [
                'robots' => false,
                'sitemap' => false,
                'key' => false,
            ];

            if (($settings['robotsEnable'] ?? '1') === '1') {
                self::writeFile('robots.txt', self::buildRobots($settings));
                $result['robots'] = true;
            } else {
                self::deleteRelative('robots.txt');
            }

            if (($settings['sitemapEnable'] ?? '1') === '1') {
                self::buildSitemap($settings);
                $result['sitemap'] = true;
            } else {
                self::deleteRelative('sitemap.xml');
                self::deleteRelative('sitemap.txt');
                self::removeChunkFiles();
            }

            if (($settings['indexNowEnable'] ?? '0') === '1' && !empty($settings['indexNowKey'])) {
                $relative = Settings::keyRelativePath($settings);
                if ($relative !== '') {
                    self::writeRelativeFile($relative, (string) $settings['indexNowKey']);
                    $result['key'] = true;
                }
            } else {
                $relative = Settings::keyRelativePath($settings);
                if ($relative !== '') {
                    self::deleteRelative($relative);
                }
            }

            Settings::cache()->set('renewseo:last_sync', time(), 86400);
            self::storeStateStamp($settings);
            Log::write('file', 'sync', 'info', $reason, 'SEO 文件已同步', $result);
            return ['ok' => true] + $result;
        } catch (\Throwable $e) {
            Log::write('file', 'sync', 'error', $reason, $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            if ($cache->enabled()) {
                $cache->unlock($lockKey);
            }
        }
    }

    public static function syncIfNeeded(string $reason = 'runtime'): void
    {
        if (self::$checked) {
            return;
        }

        self::$checked = true;
        $cache = Settings::cache();
        if (!$cache->enabled()) {
            return;
        }

        try {
            $settings = Settings::load();
            $current = self::stateStamp($settings);
            $hit = false;
            $stored = (string) $cache->get('renewseo:last_state', $hit);

            if ($hit && $stored !== '' && hash_equals($stored, $current)) {
                return;
            }

            if (($settings['enabled'] ?? '1') === '1' && !self::shouldSyncNow($settings)) {
                return;
            }

            $result = self::sync($reason, true);
            if (empty($result['ok']) && empty($result['disabled'])) {
                Log::write('file', 'syncIfNeeded', 'notice', $reason, (string) ($result['message'] ?? 'sync skipped'));
            }
        } catch (\Throwable $e) {
            Log::write('file', 'syncIfNeeded', 'error', $reason, $e->getMessage());
        }
    }

    public static function shouldSyncNow(array $settings): bool
    {
        $debounce = (int) ($settings['sitemapDebounce'] ?? 15);
        if ($debounce <= 0) {
            return true;
        }

        $cache = Settings::cache();
        if (!$cache->enabled()) {
            return true;
        }

        $hit = false;
        $last = (int) $cache->get('renewseo:last_sync', $hit);
        return !$hit || (time() - $last) >= $debounce;
    }

    public static function buildRobots(array $settings): string
    {
        $allowed = self::toLines((string) ($settings['robotsAllowed'] ?? ''));
        $denied = self::toLines((string) ($settings['robotsDenied'] ?? ''));
        $blocked = self::toLines((string) ($settings['robotsBlocked'] ?? ''));
        $mode = (string) ($settings['robotsMode'] ?? 'default_only');
        $default = (string) ($settings['robotsDefault'] ?? 'allow');

        $lines = [
            '# RenewSEO generated robots.txt',
            '# ' . date('Y-m-d H:i:s'),
            '',
        ];

        foreach ($allowed as $agent) {
            $lines[] = 'User-agent: ' . $agent;
            if ($mode === 'all') {
                foreach ($blocked as $path) {
                    $lines[] = 'Disallow: ' . $path;
                }
                if (empty($blocked)) {
                    $lines[] = 'Allow: /';
                }
            } else {
                $lines[] = 'Allow: /';
            }
            $lines[] = '';
        }

        foreach ($denied as $agent) {
            $lines[] = 'User-agent: ' . $agent;
            $lines[] = 'Disallow: /';
            if ($mode === 'all') {
                foreach ($blocked as $path) {
                    $lines[] = 'Disallow: ' . $path;
                }
            }
            $lines[] = '';
        }

        $lines[] = 'User-agent: *';
        if ($default === 'deny') {
            $lines[] = 'Disallow: /';
        } elseif (!empty($blocked)) {
            foreach ($blocked as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
            $lines[] = 'Allow: /';
        } else {
            $lines[] = 'Allow: /';
        }
        $lines[] = '';

        if (($settings['robotsSitemap'] ?? '1') === '1') {
            foreach (self::sitemapUrls($settings) as $url) {
                $lines[] = 'Sitemap: ' . $url;
            }
        }

        foreach (self::toLines((string) ($settings['robotsCustomSitemaps'] ?? '')) as $url) {
            $lines[] = 'Sitemap: ' . $url;
        }

        $extra = trim((string) ($settings['robotsExtra'] ?? ''));
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = trim($extra);
        }

        return implode("\n", $lines) . "\n";
    }

    public static function status(array $settings): array
    {
        $files = [
            'robots.txt' => 'robots.txt',
            'sitemap.xml' => 'sitemap.xml',
            'sitemap.txt' => 'sitemap.txt',
        ];

        foreach (self::chunkFiles() as $relative) {
            $files[$relative] = $relative;
        }

        $key = Settings::keyRelativePath($settings);
        if ($key !== '') {
            $files[$key] = $key;
        }

        $result = [];
        foreach ($files as $label => $relative) {
            $path = Settings::rootPath($relative);
            $exists = is_file($path);
            $result[] = [
                'name' => $label,
                'path' => $relative,
                'url' => Settings::rootUrl($relative),
                'exists' => $exists,
                'size' => $exists ? (int) @filesize($path) : 0,
                'mtime' => $exists ? (int) @filemtime($path) : 0,
            ];
        }

        return $result;
    }

    public static function contentItem(int $cid): ?array
    {
        try {
            $db = Db::get();
            $row = $db->fetchRow(
                $db->select('cid', 'slug', 'type', 'status', 'created', 'modified')
                    ->from('table.contents')
                    ->where('cid = ? AND type IN (?, ?)', $cid, 'post', 'page')
                    ->limit(1)
            );
            if (!$row) {
                return null;
            }

            return [
                'cid' => (int) $row['cid'],
                'type' => (string) $row['type'],
                'status' => (string) $row['status'],
                'created' => (int) ($row['created'] ?? 0),
                'modified' => (int) ($row['modified'] ?? 0),
                'url' => self::contentUrl($row),
            ];
        } catch (\Throwable $e) {
            Log::write('file', 'contentItem', 'error', (string) $cid, $e->getMessage());
            return null;
        }
    }

    public static function cleanupTransition(array $old, array $new): void
    {
        $oldKey = Settings::keyRelativePath($old);
        $newKey = Settings::keyRelativePath($new);
        if ($oldKey !== '' && $oldKey !== $newKey) {
            self::deleteRelative($oldKey);
        }
    }

    public static function removeGenerated(?array $settings = null): void
    {
        $settings = $settings ?? Settings::load();
        self::deleteRelative('robots.txt');
        self::deleteRelative('sitemap.xml');
        self::deleteRelative('sitemap.txt');
        self::removeChunkFiles();
        $key = Settings::keyRelativePath($settings);
        if ($key !== '') {
            self::deleteRelative($key);
        }
    }

    private static function buildSitemap(array $settings): void
    {
        $items = self::collectUrls($settings);
        $split = max(100, min(50000, (int) ($settings['sitemapSplit'] ?? 1000)));
        $chunks = array_chunk(array_values($items), $split);
        self::removeChunkFiles();

        if (count($chunks) <= 1) {
            self::writeFile('sitemap.xml', self::buildUrlset($chunks[0] ?? []));
        } else {
            $index = [];
            foreach ($chunks as $offset => $chunk) {
                $name = 'sitemap-' . ($offset + 1) . '.xml';
                self::writeFile($name, self::buildUrlset($chunk));
                $index[] = [
                    'loc' => Settings::rootUrl($name),
                    'lastmod' => date('c'),
                ];
            }
            self::writeFile('sitemap.xml', self::buildIndex($index));
        }

        if (($settings['sitemapTxt'] ?? '1') === '1') {
            $lines = array_map(static fn(array $item): string => (string) $item['loc'], array_values($items));
            self::writeFile('sitemap.txt', implode("\n", $lines) . "\n");
        } else {
            self::deleteRelative('sitemap.txt');
        }
    }

    private static function collectUrls(array $settings): array
    {
        $items = [];
        $now = time();

        self::add($items, [
            'loc' => Settings::siteUrl(),
            'lastmod' => date('c', $now),
            'priority' => (string) ($settings['sitemapPriorityHome'] ?? '1.0'),
            'changefreq' => (string) ($settings['sitemapFreqHome'] ?? 'daily'),
        ]);

        $archiveUrl = Router::url('archive', null, (string) Settings::options()->index);
        if ($archiveUrl !== '#' && rtrim($archiveUrl, '/') !== rtrim(Settings::siteUrl(), '/')) {
            self::add($items, [
                'loc' => $archiveUrl,
                'lastmod' => date('c', $now),
                'priority' => (string) ($settings['sitemapPriorityHome'] ?? '1.0'),
                'changefreq' => (string) ($settings['sitemapFreqHome'] ?? 'daily'),
            ]);
        }

        $db = Db::get();
        $contents = $db->fetchAll(
            $db->select('cid', 'slug', 'type', 'created', 'modified')
                ->from('table.contents')
                ->where('status = ? AND type IN (?, ?)', 'publish', 'post', 'page')
                ->order('modified', Db::SORT_DESC)
        );

        foreach ($contents as $row) {
            $url = self::contentUrl($row);
            if ($url === '') {
                continue;
            }
            $isPage = (string) $row['type'] === 'page';
            if ($isPage && ($settings['sitemapPage'] ?? '1') !== '1') {
                continue;
            }
            self::add($items, [
                'loc' => $url,
                'lastmod' => date('c', max((int) ($row['modified'] ?? 0), (int) ($row['created'] ?? 0), $now)),
                'priority' => (string) ($isPage ? ($settings['sitemapPriorityPage'] ?? '0.7') : ($settings['sitemapPriorityPost'] ?? '0.8')),
                'changefreq' => (string) ($isPage ? ($settings['sitemapFreqPage'] ?? 'monthly') : ($settings['sitemapFreqPost'] ?? 'weekly')),
            ]);
        }

        if (($settings['sitemapCategory'] ?? '1') === '1') {
            $rows = $db->fetchAll(
                $db->select('mid', 'slug')
                    ->from('table.metas')
                    ->where('type = ? AND count > 0', 'category')
            );
            foreach ($rows as $row) {
                $url = Router::url('category', ['slug' => $row['slug']], (string) Settings::options()->index);
                if ($url !== '#') {
                    self::add($items, [
                        'loc' => $url,
                        'lastmod' => date('c', $now),
                        'priority' => (string) ($settings['sitemapPriorityCategory'] ?? '0.6'),
                        'changefreq' => (string) ($settings['sitemapFreqTaxonomy'] ?? 'daily'),
                    ]);
                }
            }
        }

        if (($settings['sitemapTag'] ?? '1') === '1') {
            $rows = $db->fetchAll(
                $db->select('mid', 'slug')
                    ->from('table.metas')
                    ->where('type = ? AND count > 0', 'tag')
            );
            foreach ($rows as $row) {
                $url = Router::url('tag', ['slug' => $row['slug']], (string) Settings::options()->index);
                if ($url !== '#') {
                    self::add($items, [
                        'loc' => $url,
                        'lastmod' => date('c', $now),
                        'priority' => (string) ($settings['sitemapPriorityTag'] ?? '0.5'),
                        'changefreq' => (string) ($settings['sitemapFreqTaxonomy'] ?? 'daily'),
                    ]);
                }
            }
        }

        if (($settings['sitemapAuthor'] ?? '0') === '1') {
            $rows = $db->fetchAll(
                $db->select('authorId')
                    ->from('table.contents')
                    ->where('status = ? AND type = ?', 'publish', 'post')
                    ->group('authorId')
            );
            foreach ($rows as $row) {
                $uid = (int) ($row['authorId'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $url = Router::url('author', ['uid' => $uid], (string) Settings::options()->index);
                if ($url !== '#') {
                    self::add($items, [
                        'loc' => $url,
                        'lastmod' => date('c', $now),
                        'priority' => (string) ($settings['sitemapPriorityAuthor'] ?? '0.4'),
                        'changefreq' => (string) ($settings['sitemapFreqTaxonomy'] ?? 'daily'),
                    ]);
                }
            }
        }

        return $items;
    }

    private static function contentUrl(array $row): string
    {
        $params = [
            'cid' => $row['cid'] ?? 0,
            'slug' => $row['slug'] ?? '',
            'year' => date('Y', (int) ($row['created'] ?? time())),
            'month' => date('m', (int) ($row['created'] ?? time())),
            'day' => date('d', (int) ($row['created'] ?? time())),
        ];

        $url = Router::url((string) ($row['type'] ?? 'post'), $params, (string) Settings::options()->index);
        return $url === '#' ? '' : $url;
    }

    private static function buildUrlset(array $items): string
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($items as $item) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . self::xml((string) $item['loc']) . '</loc>';
            $xml[] = '    <lastmod>' . self::xml((string) $item['lastmod']) . '</lastmod>';
            $xml[] = '    <changefreq>' . self::xml((string) $item['changefreq']) . '</changefreq>';
            $xml[] = '    <priority>' . self::xml((string) $item['priority']) . '</priority>';
            $xml[] = '  </url>';
        }
        $xml[] = '</urlset>';
        return implode("\n", $xml) . "\n";
    }

    private static function buildIndex(array $files): string
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $xml[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($files as $item) {
            $xml[] = '  <sitemap>';
            $xml[] = '    <loc>' . self::xml((string) $item['loc']) . '</loc>';
            $xml[] = '    <lastmod>' . self::xml((string) $item['lastmod']) . '</lastmod>';
            $xml[] = '  </sitemap>';
        }
        $xml[] = '</sitemapindex>';
        return implode("\n", $xml) . "\n";
    }

    private static function sitemapUrls(array $settings): array
    {
        $urls = [Settings::rootUrl('sitemap.xml')];
        if (($settings['sitemapTxt'] ?? '1') === '1') {
            $urls[] = Settings::rootUrl('sitemap.txt');
        }
        return $urls;
    }

    private static function add(array &$items, array $item): void
    {
        $loc = trim((string) ($item['loc'] ?? ''));
        if ($loc === '') {
            return;
        }
        $items[$loc] = $item;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function toLines(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $clean[] = $line;
            }
        }
        return array_values(array_unique($clean));
    }

    private static function removeChunkFiles(): void
    {
        foreach (glob(Settings::rootPath('sitemap-*.xml')) ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function writeFile(string $name, string $content): void
    {
        self::writeRelativeFile($name, $content);
    }

    private static function deleteRelative(string $relative): void
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        if ($relative === '' || strpos($relative, '..') !== false) {
            return;
        }

        $path = Settings::rootPath($relative);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function writeRelativeFile(string $relative, string $content): void
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        if ($relative === '' || strpos($relative, '..') !== false) {
            throw new \RuntimeException('invalid relative path');
        }

        $path = Settings::rootPath($relative);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('unable to create directory: ' . $dir);
        }

        $temp = tempnam($dir, 'rseo_');
        if ($temp === false) {
            throw new \RuntimeException('unable to create temp file');
        }

        if (@file_put_contents($temp, $content) === false) {
            @unlink($temp);
            throw new \RuntimeException('unable to write temp file');
        }

        if (is_file($path) && !@unlink($path)) {
            @unlink($temp);
            throw new \RuntimeException('unable to replace file: ' . $relative);
        }

        if (!@rename($temp, $path)) {
            if (@copy($temp, $path)) {
                @unlink($temp);
            } else {
                @unlink($temp);
                throw new \RuntimeException('unable to move file: ' . $relative);
            }
        }
    }

    private static function chunkFiles(): array
    {
        $root = Settings::rootPath();
        $pattern = $root . DIRECTORY_SEPARATOR . 'sitemap-*.xml';
        $files = glob($pattern) ?: [];
        $items = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $items[] = basename($file);
        }

        sort($items, SORT_NATURAL);
        return $items;
    }

    private static function stateStamp(array $settings): string
    {
        $db = Db::get();
        $content = $db->fetchObject(
            $db->select([
                'COUNT(*)' => 'total',
                'MAX(cid)' => 'max_cid',
                'MAX(created)' => 'max_created',
                'MAX(modified)' => 'max_modified',
            ])->from('table.contents')
                ->where('status = ? AND type IN (?, ?)', 'publish', 'post', 'page')
        );
        $metas = $db->fetchAll(
            $db->select('mid', 'slug', 'parent', 'type', 'count')
                ->from('table.metas')
                ->where('type IN (?, ?) AND count > 0', 'category', 'tag')
                ->order('mid', Db::SORT_ASC)
        );

        $state = [
            'enabled' => (string) ($settings['enabled'] ?? '1'),
            'siteUrl' => Settings::siteUrl(),
            'indexUrl' => Settings::indexUrl(),
            'frontPage' => (string) (Settings::options()->frontPage ?? ''),
            'frontArchive' => (string) (Settings::options()->frontArchive ?? ''),
            'routingTable' => Settings::options()->routingTable ?? [],
            'content' => [
                'total' => (int) ($content->total ?? 0),
                'max_cid' => (int) ($content->max_cid ?? 0),
                'max_created' => (int) ($content->max_created ?? 0),
                'max_modified' => (int) ($content->max_modified ?? 0),
            ],
            'metas' => array_map(static function (array $row): array {
                return [
                    'mid' => (int) ($row['mid'] ?? 0),
                    'slug' => (string) ($row['slug'] ?? ''),
                    'parent' => (int) ($row['parent'] ?? 0),
                    'type' => (string) ($row['type'] ?? ''),
                    'count' => (int) ($row['count'] ?? 0),
                ];
            }, $metas),
        ];

        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return sha1(is_string($json) ? $json : serialize($state));
    }

    private static function storeStateStamp(array $settings): void
    {
        $cache = Settings::cache();
        if (!$cache->enabled()) {
            return;
        }

        try {
            $cache->set('renewseo:last_state', self::stateStamp($settings), 2592000);
        } catch (\Throwable $e) {
            Log::write('file', 'storeState', 'error', '', $e->getMessage());
        }
    }
}
