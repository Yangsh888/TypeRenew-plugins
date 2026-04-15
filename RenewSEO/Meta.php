<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Meta
{
    public static function archive($archive): void
    {
        Files::syncIfNeeded('archive');
        $settings = Settings::load();
        if (($settings['enabled'] ?? '1') !== '1') {
            return;
        }

        if (($settings['canonicalEnable'] ?? '1') === '1') {
            $canonical = self::canonical($archive, $settings);
            if ($canonical !== '' && $archive->is('single')) {
                $archive->setArchiveUrl($canonical);
            }
        }

        if ($archive->is('single')) {
            $title = self::field($archive, 'seo_title');
            $desc = self::field($archive, 'seo_desc');
            $keys = self::field($archive, 'seo_keys');

            if ($title !== '') {
                $archive->setArchiveTitle($title);
            }
            if ($desc !== '') {
                $archive->setArchiveDescription($desc);
            } elseif (self::archiveDescription($archive) === '') {
                $archive->setArchiveDescription(self::summary($archive));
            }
            if ($keys !== '') {
                $archive->setArchiveKeywords($keys);
            }
        }

        if ($archive->is('error404')) {
            $archive->setArchiveDescription('请求的页面不存在或已失效');
            Log::record404($archive->request);
        }
    }

    public static function headerOptions(array $allows, $archive): array
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '1') !== '1') {
            return $allows;
        }

        $state = self::state($archive, $settings);

        if ($state['description'] !== '') {
            $allows['description'] = Text::e($state['description']);
        }
        if ($state['keywords'] !== '') {
            $allows['keywords'] = Text::e($state['keywords']);
        }
        if (($settings['ogEnable'] ?? '1') === '1') {
            $allows['social'] = '';
        }

        return $allows;
    }

    public static function header(string $header, $archive): void
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '1') !== '1') {
            return;
        }

        $lines = [];
        $state = self::state($archive, $settings);

        if ($state['canonical'] !== '' && !$archive->is('single')) {
            $lines[] = '<link rel="canonical" href="' . Text::e($state['canonical']) . '" />';
        }

        $robots = self::robotsMeta($archive, $settings);
        if ($robots !== '') {
            $lines[] = '<meta name="robots" content="' . Text::e($robots) . '" />';
        }

        if (($settings['ogEnable'] ?? '1') === '1') {
            $lines = array_merge($lines, self::ogLines($archive, $state));
        }

        if (($settings['timeEnable'] ?? '1') === '1') {
            $lines = array_merge($lines, self::timeFactorLines($archive, $state));
        }

        if (!empty($lines)) {
            echo implode("\n", $lines) . "\n";
        }
    }

    public static function contentEx(?string $content, $widget): ?string
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '1') !== '1' || ($settings['altEnable'] ?? '1') !== '1' || !$widget->is('single')) {
            return $content;
        }

        if (!is_string($content) || stripos($content, '<img') === false) {
            return $content;
        }

        $alt = str_replace(
            ['{title}', '{site}'],
            [(string) ($widget->title ?? ''), Settings::siteName()],
            (string) ($settings['altTemplate'] ?? '{title} - {site}')
        );
        $alt = trim($alt);
        if ($alt === '') {
            return $content;
        }

        return preg_replace_callback('/<img\b[^>]*>/i', static function (array $match) use ($alt): string {
            $tag = (string) $match[0];
            if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $m)) {
                if (trim((string) $m[2]) !== '') {
                    return $tag;
                }
                return (string) preg_replace(
                    '/\balt\s*=\s*(["\'])(.*?)\1/i',
                    'alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"',
                    $tag,
                    1
                );
            }
            $closing = preg_match('#/\s*>$#', $tag) === 1 ? ' />' : '>';
            $body = preg_replace('#\s*/?>$#', '', $tag) ?? $tag;
            return rtrim($body) . ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' . $closing;
        }, $content);
    }

    public static function fields($item): void
    {
        self::renderFields($item);
    }

    private static function renderFields($item): void
    {
        $cid = method_exists($item, 'have') && $item->have() ? (int) ($item->cid ?? 0) : 0;
        $type = (string) ($item->type ?? 'post');
        $title = self::field($item, 'seo_title');
        $desc = self::field($item, 'seo_desc');
        $keys = self::field($item, 'seo_keys');
        $canonical = self::field($item, 'seo_canonical');
        $image = self::field($item, 'seo_image');
        $robots = self::field($item, 'seo_robots');
        ?>
        <section class="typecho-post-option">
            <label class="typecho-label"><?php _e('SEO 信息'); ?></label>
            <p><input type="text" class="w-100 text" name="fields[seo_title]" value="<?php echo Text::e($title); ?>" placeholder="<?php _e('SEO 标题（留空则沿用内容标题）'); ?>"></p>
            <p><textarea class="w-100" name="fields[seo_desc]" rows="3" placeholder="<?php _e('SEO 描述（留空则自动摘取摘要）'); ?>"><?php echo Text::e($desc); ?></textarea></p>
            <p><input type="text" class="w-100 text" name="fields[seo_keys]" value="<?php echo Text::e($keys); ?>" placeholder="<?php _e('SEO 关键词，多个用逗号分隔'); ?>"></p>
        </section>
        <section class="typecho-post-option">
            <label class="typecho-label"><?php _e('SEO 技术项'); ?></label>
            <p><input type="url" class="w-100 text mono" name="fields[seo_canonical]" value="<?php echo Text::e($canonical); ?>" placeholder="<?php _e('Canonical 覆盖地址（留空则自动生成）'); ?>"></p>
            <p><input type="text" class="w-100 text mono" name="fields[seo_image]" value="<?php echo Text::e($image); ?>" placeholder="<?php _e('OG 图片地址（支持相对路径或绝对地址）'); ?>"></p>
            <p>
                <select name="fields[seo_robots]">
                    <option value=""<?php echo $robots === '' ? ' selected' : ''; ?>><?php _e('自动'); ?></option>
                    <option value="index,follow"<?php echo $robots === 'index,follow' ? ' selected' : ''; ?>>index,follow</option>
                    <option value="noindex,follow"<?php echo $robots === 'noindex,follow' ? ' selected' : ''; ?>>noindex,follow</option>
                    <option value="index,nofollow"<?php echo $robots === 'index,nofollow' ? ' selected' : ''; ?>>index,nofollow</option>
                    <option value="noindex,nofollow"<?php echo $robots === 'noindex,nofollow' ? ' selected' : ''; ?>>noindex,nofollow</option>
                </select>
            </p>
            <?php if ($cid > 0): ?>
                <p class="description"><?php echo $type === 'post' ? _t('当前文章') : _t('当前页面'); ?> CID: <?php echo $cid; ?></p>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function canonical($archive, array $settings): string
    {
        $override = self::field($archive, 'seo_canonical');
        if ($override !== '') {
            return Settings::absoluteUrl($override);
        }

        $url = (string) ($archive->request->getRequestUrl() ?? '');
        if ($archive->is('single')) {
            $url = (string) ($archive->permalink ?? self::archiveUrl($archive) ?? $url);
        }
        if ($url === '') {
            $url = self::archiveUrl($archive);
        }
        if ($url === '') {
            $url = Settings::siteUrl();
        }

        return self::stripParams($url, (string) ($settings['canonicalStrip'] ?? ''));
    }

    private static function stripParams(string $url, string $rules): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $strip = preg_split('/\r\n|\r|\n/', $rules) ?: [];
        $names = [];
        foreach ($strip as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $names[] = $item;
            }
        }

        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
            foreach (array_keys($query) as $key) {
                foreach ($names as $name) {
                    if ($name === $key || ($name === 'utm_*' && str_starts_with($key, 'utm_'))) {
                        unset($query[$key]);
                        break;
                    }
                }
            }
            $parts['query'] = http_build_query($query);
        }

        return self::buildUrl($parts);
    }

    private static function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user !== '' || $pass !== '') ? $pass . '@' : '';
        $path = $parts['path'] ?? '';
        $query = !empty($parts['query']) ? '?' . $parts['query'] : '';
        return $scheme . $user . $pass . $host . $port . $path . $query;
    }

    private static function robotsMeta($archive, array $settings): string
    {
        $custom = self::field($archive, 'seo_robots');
        if ($custom !== '') {
            return $custom;
        }

        if ($archive->is('error404') && ($settings['noindex404'] ?? '1') === '1') {
            return 'noindex,nofollow';
        }
        if ($archive->is('search') && ($settings['noindexSearch'] ?? '1') === '1') {
            return 'noindex,follow';
        }
        return '';
    }

    private static function ogLines($archive, array $state): array
    {
        $image = $state['image'];

        $lines = [
            '<meta property="og:type" content="' . Text::e($state['type']) . '" />',
            '<meta property="og:url" content="' . Text::e($state['url']) . '" />',
            '<meta property="og:title" content="' . Text::e($state['title']) . '" />',
            '<meta property="og:description" content="' . Text::e($state['description']) . '" />',
            '<meta property="og:site_name" content="' . Text::e(Settings::siteName()) . '" />',
            '<meta name="twitter:card" content="' . Text::e($image === '' ? 'summary' : 'summary_large_image') . '" />',
            '<meta name="twitter:title" content="' . Text::e($state['title']) . '" />',
            '<meta name="twitter:description" content="' . Text::e($state['description']) . '" />',
        ];

        if ($image !== '') {
            $lines[] = '<meta property="og:image" content="' . Text::e($image) . '" />';
            $lines[] = '<meta name="twitter:image" content="' . Text::e($image) . '" />';
        }

        if ($archive->is('single') && $state['publishedAt'] !== '') {
            $lines[] = '<meta property="article:published_time" content="' . Text::e($state['publishedAt']) . '" />';
        }
        if ($archive->is('single') && $state['updatedAt'] !== '') {
            $lines[] = '<meta property="article:modified_time" content="' . Text::e($state['updatedAt']) . '" />';
        }

        return $lines;
    }

    private static function timeFactorLines($archive, array $state): array
    {
        if (!$archive->is('single') || $archive->is('error404')) {
            return [];
        }

        if ($state['publishedAt'] === '' || $state['updatedAt'] === '') {
            return [];
        }

        $json = json_encode([
            '@context' => 'https://ziyuan.baidu.com/contexts/cambrian.jsonld',
            '@id' => $state['url'],
            'pubDate' => $state['publishedAt'],
            'upDate' => $state['updatedAt'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        if (!is_string($json) || $json === '') {
            return [];
        }

        return [
            '<meta property="bytedance:published_time" content="' . Text::e($state['publishedAt']) . '" />',
            '<meta property="bytedance:updated_time" content="' . Text::e($state['updatedAt']) . '" />',
            '<meta property="bytedance:lrDate_time" content="' . Text::e($state['updatedAt']) . '" />',
            '<script type="application/ld+json">' . $json . '</script>',
        ];
    }

    private static function image($archive, array $settings): string
    {
        $custom = self::field($archive, 'seo_image');
        if ($custom !== '') {
            return Settings::absoluteUrl($custom);
        }

        if (!empty($archive->fields) && isset($archive->fields->bannerUrl) && (string) $archive->fields->bannerUrl !== '') {
            return Settings::absoluteUrl((string) $archive->fields->bannerUrl);
        }

        $raw = (string) ($archive->text ?? '');
        if ($raw !== '') {
            if (preg_match('/<img[^>]+src\s*=\s*(["\'])(.*?)\1/i', $raw, $m)) {
                return Settings::absoluteUrl((string) $m[2]);
            }
            if (preg_match('/!\[[^\]]*]\(([^)\s]+)(?:\s+"[^"]*")?\)/', $raw, $m)) {
                return Settings::absoluteUrl((string) $m[1]);
            }
        }

        return Settings::absoluteUrl((string) ($settings['ogDefaultImage'] ?? ''));
    }

    private static function summary($archive): string
    {
        $summary = '';
        if (isset($archive->excerpt)) {
            $summary = (string) $archive->excerpt;
        } elseif (isset($archive->text)) {
            $summary = (string) $archive->text;
        }

        $summary = strip_tags($summary);
        $summary = preg_replace('/\s+/u', ' ', $summary) ?? '';
        return trim(Text::slice($summary, 180));
    }

    private static function field($item, string $name): string
    {
        $fields = $item->fields ?? null;
        if (is_object($fields) && isset($fields->{$name})) {
            return trim((string) $fields->{$name});
        }
        if (is_array($fields) && isset($fields[$name])) {
            return trim((string) $fields[$name]);
        }
        return '';
    }

    private static function state($archive, array $settings): array
    {
        $title = self::archiveTitle($archive);
        if ($title === '') {
            $title = Settings::siteName();
        }

        $description = self::archiveDescription($archive);
        if ($description === '' && $archive->is('single')) {
            $description = self::summary($archive);
        }
        if ($description === '' && !$archive->is('error404')) {
            $description = trim((string) (Settings::options()->description ?? ''));
        }

        $keywords = self::archiveKeywords($archive);
        if ($keywords === '') {
            $keywords = trim((string) (Settings::options()->keywords ?? ''));
        }

        $canonical = ($settings['canonicalEnable'] ?? '1') === '1' ? self::canonical($archive, $settings) : '';
        $url = $canonical !== '' ? $canonical : self::archiveUrl($archive);
        if ($url === '') {
            $url = Settings::siteUrl();
        }

        $created = $archive->is('single') ? (int) ($archive->created ?? 0) : 0;
        $modified = $created > 0 ? max((int) ($archive->modified ?? 0), $created) : 0;

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'url' => $url,
            'image' => self::image($archive, $settings),
            'type' => $archive->is('single') ? 'article' : 'website',
            'publishedAt' => $created > 0 ? date(DATE_ATOM, $created) : '',
            'updatedAt' => $modified > 0 ? date(DATE_ATOM, $modified) : '',
        ];
    }

    private static function archiveTitle($archive): string
    {
        if (method_exists($archive, 'getArchiveTitle')) {
            return trim((string) $archive->getArchiveTitle());
        }
        return '';
    }

    private static function archiveDescription($archive): string
    {
        if (method_exists($archive, 'getArchiveDescription')) {
            return trim((string) $archive->getArchiveDescription());
        }
        return '';
    }

    private static function archiveKeywords($archive): string
    {
        if (method_exists($archive, 'getArchiveKeywords')) {
            return trim((string) $archive->getArchiveKeywords());
        }
        return '';
    }

    private static function archiveUrl($archive): string
    {
        if (method_exists($archive, 'getArchiveUrl')) {
            $url = trim((string) $archive->getArchiveUrl());
            if ($url !== '') {
                return $url;
            }
        }

        $url = trim((string) ($archive->permalink ?? ''));
        if ($url !== '') {
            return $url;
        }

        $request = $archive->request ?? null;
        if ($request && method_exists($request, 'getRequestUrl')) {
            return trim((string) $request->getRequestUrl());
        }

        return '';
    }

}
