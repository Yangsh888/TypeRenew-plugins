<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

use Typecho\Db;
use Typecho\Router;
use Widget\Contents\From as ContentWidget;
use Widget\Contents\Page\Rows as PageRows;
use Widget\Metas\Category\Related as CategoryRelated;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Files
{
    private static bool $checked = false;

    public static function sync(string $reason = 'manual', bool $force = false, bool $writeInfoLog = true): array
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '1') !== '1') {
            self::removeGenerated($settings);
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
                self::writeRelativeFile('robots.txt', self::buildRobots($settings));
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
            self::clearPendingSync();
            if ($writeInfoLog) {
                Log::write('file', 'sync', 'info', $reason, 'SEO 文件已同步', $result);
            }
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

        try {
            $settings = Settings::load();
            if (($settings['enabled'] ?? '1') !== '1') {
                return;
            }

            if (self::hasExpectedFiles($settings) && !self::hasPendingSync()) {
                return;
            }

            if (self::hasPendingSync() && !self::shouldSyncNow($settings)) {
                return;
            }

            $result = self::sync($reason, true, false);
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

    public static function markPendingSync(string $reason = 'content'): void
    {
        $cache = Settings::cache();
        if (!$cache->enabled()) {
            return;
        }

        $cache->set('renewseo:pending_sync', [
            'reason' => $reason,
            'time' => time(),
        ], 86400);
    }

    public static function buildRobots(array $settings): string
    {
        $allowed = self::toLines((string) ($settings['robotsAllowed'] ?? ''));
        $denied = self::toLines((string) ($settings['robotsDenied'] ?? ''));
        $blocked = self::toLines((string) ($settings['robotsBlocked'] ?? ''));
        $mode = (string) ($settings['robotsMode'] ?? 'default_only');
        $default = (string) ($settings['robotsDefault'] ?? 'allow');

        $lines = [];

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

    private static function hasExpectedFiles(array $settings): bool
    {
        if (($settings['robotsEnable'] ?? '1') === '1' && !is_file(Settings::rootPath('robots.txt'))) {
            return false;
        }

        if (($settings['sitemapEnable'] ?? '1') === '1' && !is_file(Settings::rootPath('sitemap.xml'))) {
            return false;
        }

        if (($settings['sitemapEnable'] ?? '1') === '1'
            && ($settings['sitemapTxt'] ?? '1') === '1'
            && !is_file(Settings::rootPath('sitemap.txt'))
        ) {
            return false;
        }

        if (($settings['indexNowEnable'] ?? '0') === '1' && !empty($settings['indexNowKey'])) {
            $relative = Settings::keyRelativePath($settings);
            if ($relative !== '' && !is_file(Settings::rootPath($relative))) {
                return false;
            }
        }

        return true;
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
        $split = max(100, min(50000, (int) ($settings['sitemapSplit'] ?? 1000)));
        self::removeChunkFiles();
        $txtEnabled = ($settings['sitemapTxt'] ?? '1') === '1';
        $txtTemp = null;

        try {
            if ($txtEnabled) {
                $txtTemp = self::createTempFile(dirname(Settings::rootPath('sitemap.txt')));
            }

            $state = [
                'split' => $split,
                'generatedAt' => date('c'),
                'seen' => [],
                'buffer' => [],
                'chunkCount' => 0,
                'index' => [],
                'multi' => false,
                'txtEnabled' => $txtEnabled,
                'txtTemp' => $txtTemp,
                'txtBuffer' => '',
            ];

            foreach (self::iterateSitemapItems($settings) as $item) {
                self::appendSitemapItem($state, $item);
            }

            self::finalizeSitemap($state);
        } finally {
            if ($txtTemp !== null && is_file($txtTemp)) {
                @unlink($txtTemp);
            }
        }
    }

    private static function iterateSitemapItems(array $settings): \Generator
    {
        $now = time();

        yield [
            'loc' => Settings::siteUrl(),
            'lastmod' => date('c', $now),
            'priority' => (string) ($settings['sitemapPriorityHome'] ?? '1.0'),
            'changefreq' => (string) ($settings['sitemapFreqHome'] ?? 'daily'),
        ];

        $archiveUrl = Router::url('archive', null, (string) Settings::options()->index);
        if ($archiveUrl !== '#' && rtrim($archiveUrl, '/') !== rtrim(Settings::siteUrl(), '/')) {
            yield [
                'loc' => $archiveUrl,
                'lastmod' => date('c', $now),
                'priority' => (string) ($settings['sitemapPriorityHome'] ?? '1.0'),
                'changefreq' => (string) ($settings['sitemapFreqHome'] ?? 'daily'),
            ];
        }

        $db = Db::get();
        foreach (self::iteratePublishedContents($settings) as [$row, $fields, $routeContext]) {
            $item = self::buildContentSitemapItem($row, $fields, $routeContext, $settings, $now);
            if ($item !== null) {
                yield $item;
            }
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
                    yield [
                        'loc' => $url,
                        'lastmod' => date('c', $now),
                        'priority' => (string) ($settings['sitemapPriorityCategory'] ?? '0.6'),
                        'changefreq' => (string) ($settings['sitemapFreqTaxonomy'] ?? 'daily'),
                    ];
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
                    yield [
                        'loc' => $url,
                        'lastmod' => date('c', $now),
                        'priority' => (string) ($settings['sitemapPriorityTag'] ?? '0.5'),
                        'changefreq' => (string) ($settings['sitemapFreqTaxonomy'] ?? 'daily'),
                    ];
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
                    yield [
                        'loc' => $url,
                        'lastmod' => date('c', $now),
                        'priority' => (string) ($settings['sitemapPriorityAuthor'] ?? '0.4'),
                        'changefreq' => (string) ($settings['sitemapFreqTaxonomy'] ?? 'daily'),
                    ];
                }
            }
        }
    }

    private static function contentUrl(array $row): string
    {
        $cid = (int) ($row['cid'] ?? 0);
        if ($cid <= 0) {
            return '';
        }

        try {
            $widget = ContentWidget::allocWithAlias('renewseo-sitemap-' . $cid, ['cid' => $cid]);
            if ($widget->have()) {
                return (string) $widget->permalink;
            }
        } catch (\Throwable $e) {
            Log::write('file', 'contentUrl', 'notice', (string) $cid, $e->getMessage());
        }

        $params = [
            'cid' => $row['cid'] ?? 0,
            'slug' => $row['slug'] ?? '',
            'category' => self::contentCategorySlug($cid),
            'directory' => self::contentDirectory((string) ($row['type'] ?? 'post'), $cid),
            'year' => date('Y', (int) ($row['created'] ?? time())),
            'month' => date('m', (int) ($row['created'] ?? time())),
            'day' => date('d', (int) ($row['created'] ?? time())),
        ];

        $url = Router::url((string) ($row['type'] ?? 'post'), $params, (string) Settings::options()->index);
        return $url === '#' ? '' : $url;
    }

    private static function contentCategorySlug(int $cid): string
    {
        try {
            $categories = CategoryRelated::allocWithAlias('renewseo-sitemap-category-' . $cid, ['cid' => $cid]);
            if ($categories->have()) {
                return (string) ($categories->slug ?? 'uncategorized');
            }
        } catch (\Throwable $e) {
            Log::write('file', 'contentCategorySlug', 'notice', (string) $cid, $e->getMessage());
        }

        return 'uncategorized';
    }

    private static function contentDirectory(string $type, int $cid): string
    {
        if ($type !== 'page') {
            return '';
        }

        try {
            $pages = PageRows::allocWithAlias('renewseo-sitemap-page-' . $cid, ['current' => $cid]);
            $parts = $pages->getAllParentsSlug($cid);
            if ($parts !== []) {
                return implode('/', array_map('urlencode', $parts));
            }
        } catch (\Throwable $e) {
            Log::write('file', 'contentDirectory', 'notice', (string) $cid, $e->getMessage());
        }

        return '';
    }

    private static function buildUrlset(array $items): string
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $hasImage = false;
        foreach ($items as $item) {
            if (!empty($item['image'])) {
                $hasImage = true;
                break;
            }
        }

        $urlset = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ($hasImage) {
            $urlset .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }
        $urlset .= '>';
        $xml[] = $urlset;
        foreach ($items as $item) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . self::xml((string) $item['loc']) . '</loc>';
            $xml[] = '    <lastmod>' . self::xml((string) $item['lastmod']) . '</lastmod>';
            $xml[] = '    <changefreq>' . self::xml((string) $item['changefreq']) . '</changefreq>';
            $xml[] = '    <priority>' . self::xml((string) $item['priority']) . '</priority>';
            if (!empty($item['image'])) {
                $xml[] = '    <image:image>';
                $xml[] = '      <image:loc>' . self::xml((string) $item['image']) . '</image:loc>';
                $xml[] = '    </image:image>';
            }
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

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function iteratePublishedContents(array $settings, int $pageSize = 200): \Generator
    {
        $db = Db::get();
        $withImageData = ($settings['sitemapImage'] ?? '1') === '1';
        $page = 1;
        $context = [
            'categoryOrder' => self::categoryOrderIndex(),
            'pageNodes' => [],
            'pageDirectories' => [],
        ];

        do {
            $select = $withImageData
                ? $db->select('cid', 'slug', 'type', 'created', 'modified', 'text')
                : $db->select('cid', 'slug', 'type', 'created', 'modified');
            $rows = $db->fetchAll(
                $select
                    ->from('table.contents')
                    ->where('status = ? AND type IN (?, ?)', 'publish', 'post', 'page')
                    ->order('modified', Db::SORT_DESC)
                    ->page($page, $pageSize)
            );

            if (empty($rows)) {
                break;
            }

            $fieldsByCid = $withImageData
                ? self::contentFields(array_values(array_filter(array_map(
                    static fn(array $row): int => (int) ($row['cid'] ?? 0),
                    $rows
                ))))
                : [];
            $routeContext = self::buildBatchRouteContext($rows, $context);

            foreach ($rows as $row) {
                $cid = (int) ($row['cid'] ?? 0);
                yield [$row, $fieldsByCid[$cid] ?? [], $routeContext[$cid] ?? []];
            }

            $page++;
        } while (count($rows) === $pageSize);
    }

    private static function buildContentSitemapItem(array $row, array $fields, array $routeContext, array $settings, int $now): ?array
    {
        $url = self::contentUrlFromRouteContext($row, $routeContext);
        if ($url === '') {
            $url = self::contentUrl($row);
        }
        if ($url === '') {
            return null;
        }

        $isPage = (string) $row['type'] === 'page';
        if (!$isPage && ($settings['sitemapPost'] ?? '1') !== '1') {
            return null;
        }
        if ($isPage && ($settings['sitemapPage'] ?? '1') !== '1') {
            return null;
        }

        $lastmod = max((int) ($row['modified'] ?? 0), (int) ($row['created'] ?? 0));
        if ($lastmod <= 0) {
            $lastmod = $now;
        }

        return [
            'loc' => $url,
            'lastmod' => date('c', $lastmod),
            'priority' => (string) ($isPage ? ($settings['sitemapPriorityPage'] ?? '0.7') : ($settings['sitemapPriorityPost'] ?? '0.8')),
            'changefreq' => (string) ($isPage ? ($settings['sitemapFreqPage'] ?? 'monthly') : ($settings['sitemapFreqPost'] ?? 'weekly')),
            'image' => ($settings['sitemapImage'] ?? '1') === '1'
                ? Meta::sitemapImage($row, $fields, $settings)
                : '',
        ];
    }

    private static function buildBatchRouteContext(array $rows, array &$context): array
    {
        $cids = [];
        $pageIds = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $cids[] = $cid;
            if ((string) ($row['type'] ?? '') === 'page') {
                $pageIds[] = $cid;
            }
        }

        $result = [];
        $categoryByCid = self::batchCategorySlugs($cids, $context['categoryOrder']);
        self::warmPageNodes($pageIds, $context);

        foreach ($cids as $cid) {
            $result[$cid] = [
                'category' => $categoryByCid[$cid] ?? 'uncategorized',
                'directory' => self::pageDirectoryFor($cid, $context),
            ];
        }

        return $result;
    }

    private static function contentUrlFromRouteContext(array $row, array $routeContext): string
    {
        $cid = (int) ($row['cid'] ?? 0);
        if ($cid <= 0) {
            return '';
        }

        $created = (int) ($row['created'] ?? time());
        $params = [
            'cid' => $cid,
            'slug' => $row['slug'] ?? '',
            'category' => (string) ($routeContext['category'] ?? 'uncategorized'),
            'directory' => (string) ($routeContext['directory'] ?? ''),
            'year' => date('Y', $created),
            'month' => date('m', $created),
            'day' => date('d', $created),
        ];

        $url = Router::url((string) ($row['type'] ?? 'post'), $params, (string) Settings::options()->index);
        return $url === '#' ? '' : $url;
    }

    private static function categoryOrderIndex(): array
    {
        $rows = Db::get()->fetchAll(
            Db::get()->select('mid', 'slug', 'order', 'parent')
                ->from('table.metas')
                ->where('type = ?', 'category')
        );

        if (empty($rows)) {
            return [];
        }

        usort($rows, static function (array $a, array $b): int {
            return ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0));
        });

        $map = [];
        $children = [];
        $top = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['mid'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            $map[$mid] = $row;
        }

        foreach ($map as $mid => $row) {
            $parent = (int) ($row['parent'] ?? 0);
            if ($parent > 0 && isset($map[$parent])) {
                $children[$parent][] = $mid;
            } else {
                $top[] = $mid;
            }
        }

        $order = [];
        $index = 0;
        $walk = static function (array $ids) use (&$walk, &$children, &$order, &$index): void {
            foreach ($ids as $id) {
                $order[$id] = $index++;
                if (!empty($children[$id])) {
                    $walk($children[$id]);
                }
            }
        };
        $walk($top);

        return $order;
    }

    private static function batchCategorySlugs(array $cids, array $categoryOrder): array
    {
        if (empty($cids)) {
            return [];
        }

        $rows = Db::get()->fetchAll(
            Db::get()->select('table.relationships.cid', 'table.metas.mid', 'table.metas.slug')
                ->from('table.relationships')
                ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                ->where('table.relationships.cid IN ?', $cids)
                ->where('table.metas.type = ?', 'category')
        );

        $grouped = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            $mid = (int) ($row['mid'] ?? 0);
            if ($cid <= 0 || $mid <= 0) {
                continue;
            }
            $grouped[$cid][] = [
                'mid' => $mid,
                'slug' => (string) ($row['slug'] ?? ''),
            ];
        }

        $result = [];
        foreach ($grouped as $cid => $items) {
            usort($items, static function (array $a, array $b) use ($categoryOrder): int {
                $orderA = $categoryOrder[$a['mid']] ?? PHP_INT_MAX;
                $orderB = $categoryOrder[$b['mid']] ?? PHP_INT_MAX;
                if ($orderA === $orderB) {
                    return $a['mid'] <=> $b['mid'];
                }
                return $orderA <=> $orderB;
            });
            $result[$cid] = (string) ($items[0]['slug'] ?? 'uncategorized');
        }

        return $result;
    }

    private static function warmPageNodes(array $pageIds, array &$context): void
    {
        $pending = [];
        foreach ($pageIds as $cid) {
            $cid = (int) $cid;
            if ($cid > 0 && !isset($context['pageNodes'][$cid])) {
                $pending[$cid] = $cid;
            }
        }

        while (!empty($pending)) {
            $rows = Db::get()->fetchAll(
                Db::get()->select('cid', 'slug', 'parent')
                    ->from('table.contents')
                    ->where('type = ? AND status = ?', 'page', 'publish')
                    ->where('cid IN ?', array_values($pending))
            );

            $pending = [];
            foreach ($rows as $row) {
                $cid = (int) ($row['cid'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }

                $context['pageNodes'][$cid] = [
                    'slug' => (string) ($row['slug'] ?? ''),
                    'parent' => (int) ($row['parent'] ?? 0),
                ];

                $parent = (int) ($row['parent'] ?? 0);
                if ($parent > 0 && !isset($context['pageNodes'][$parent])) {
                    $pending[$parent] = $parent;
                }
            }
        }
    }

    private static function pageDirectoryFor(int $cid, array &$context): string
    {
        if ($cid <= 0) {
            return '';
        }

        if (isset($context['pageDirectories'][$cid])) {
            return (string) $context['pageDirectories'][$cid];
        }

        if (!isset($context['pageNodes'][$cid])) {
            $context['pageDirectories'][$cid] = '';
            return '';
        }

        $parts = [];
        $seen = [];
        $parent = (int) ($context['pageNodes'][$cid]['parent'] ?? 0);
        while ($parent > 0 && isset($context['pageNodes'][$parent]) && !isset($seen[$parent])) {
            $seen[$parent] = true;
            $slug = trim((string) ($context['pageNodes'][$parent]['slug'] ?? ''));
            if ($slug !== '') {
                $parts[] = urlencode($slug);
            }
            $parent = (int) ($context['pageNodes'][$parent]['parent'] ?? 0);
        }

        $context['pageDirectories'][$cid] = implode('/', array_reverse($parts));
        return (string) $context['pageDirectories'][$cid];
    }

    private static function appendSitemapItem(array &$state, array $item): void
    {
        $loc = trim((string) ($item['loc'] ?? ''));
        if ($loc === '' || isset($state['seen'][$loc])) {
            return;
        }

        $state['seen'][$loc] = true;
        $state['buffer'][] = $item;

        if ($state['txtEnabled']) {
            $state['txtBuffer'] .= $loc . "\n";
            if (strlen($state['txtBuffer']) >= 8192) {
                self::flushSitemapTxtBuffer($state);
            }
        }

        if (!$state['multi'] && count($state['buffer']) > $state['split']) {
            $state['multi'] = true;
            self::writeSitemapChunk($state, array_splice($state['buffer'], 0, $state['split']));
        }

        if ($state['multi'] && count($state['buffer']) >= $state['split']) {
            self::writeSitemapChunk($state, array_splice($state['buffer'], 0, $state['split']));
        }
    }

    private static function finalizeSitemap(array &$state): void
    {
        if ($state['multi']) {
            if (!empty($state['buffer'])) {
                self::writeSitemapChunk($state, $state['buffer']);
                $state['buffer'] = [];
            }
            self::writeRelativeFile('sitemap.xml', self::buildIndex($state['index']));
        } else {
            self::writeRelativeFile('sitemap.xml', self::buildUrlset($state['buffer']));
            $state['buffer'] = [];
        }

        if ($state['txtEnabled']) {
            self::flushSitemapTxtBuffer($state);
            self::moveTempFileToRelative((string) $state['txtTemp'], 'sitemap.txt');
            $state['txtTemp'] = null;
        } else {
            self::deleteRelative('sitemap.txt');
        }
    }

    private static function writeSitemapChunk(array &$state, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $state['chunkCount']++;
        $name = 'sitemap-' . $state['chunkCount'] . '.xml';
        self::writeRelativeFile($name, self::buildUrlset($items));
        $state['index'][] = [
            'loc' => Settings::rootUrl($name),
            'lastmod' => (string) $state['generatedAt'],
        ];
    }

    private static function flushSitemapTxtBuffer(array &$state): void
    {
        if (!$state['txtEnabled'] || $state['txtBuffer'] === '') {
            return;
        }

        if (@file_put_contents((string) $state['txtTemp'], $state['txtBuffer'], FILE_APPEND) === false) {
            throw new \RuntimeException('unable to write sitemap txt temp file');
        }

        $state['txtBuffer'] = '';
    }

    private static function contentFields(array $cids): array
    {
        if (empty($cids)) {
            return [];
        }

        $rows = Db::get()->fetchAll(
            Db::get()->select('cid', 'name', 'type', 'str_value', 'int_value', 'float_value')
                ->from('table.fields')
                ->where('cid IN ?', $cids)
        );

        $fields = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $type = (string) ($row['type'] ?? 'str');
            $value = $type === 'json'
                ? json_decode((string) ($row['str_value'] ?? ''), true)
                : ($row[$type . '_value'] ?? null);
            $fields[$cid][(string) ($row['name'] ?? '')] = $value;
        }

        return $fields;
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

    private static function hasPendingSync(): bool
    {
        $cache = Settings::cache();
        if (!$cache->enabled()) {
            return false;
        }

        $hit = false;
        $cache->get('renewseo:pending_sync', $hit);
        return $hit;
    }

    private static function clearPendingSync(): void
    {
        $cache = Settings::cache();
        if ($cache->enabled()) {
            $cache->delete('renewseo:pending_sync');
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

        $temp = self::createTempFile($dir);
        if (@file_put_contents($temp, $content) === false) {
            throw new \RuntimeException('unable to write temp file');
        }

        self::moveTempFileToPath($temp, $path, $relative);
    }

    private static function createTempFile(string $dir): string
    {
        $temp = tempnam($dir, 'rseo_');
        if ($temp === false) {
            throw new \RuntimeException('unable to create temp file');
        }

        return $temp;
    }

    private static function moveTempFileToRelative(string $temp, string $relative): void
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        if ($relative === '' || strpos($relative, '..') !== false) {
            throw new \RuntimeException('invalid relative path');
        }

        self::moveTempFileToPath($temp, Settings::rootPath($relative), $relative);
    }

    private static function moveTempFileToPath(string $temp, string $path, string $relative): void
    {
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

}
