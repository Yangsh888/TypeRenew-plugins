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
                $archive->archiveUrl = $canonical;
            }
        }

        if ($archive->is('single')) {
            $title = self::field($archive, 'seo_title');
            $desc = self::field($archive, 'seo_desc');
            $keys = self::field($archive, 'seo_keys');

            if ($title !== '') {
                $archive->archiveTitle = $title;
            }
            if ($desc !== '') {
                $archive->setArchiveDescription($desc);
            } elseif (empty($archive->archiveDescription)) {
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

        $allows['description'] = htmlspecialchars((string) ($archive->archiveDescription ?? ''), ENT_QUOTES, 'UTF-8');
        $allows['keywords'] = htmlspecialchars((string) ($archive->archiveKeywords ?? ''), ENT_QUOTES, 'UTF-8');
        $allows['social'] = '';

        return $allows;
    }

    public static function header(string $header, $archive): void
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '1') !== '1') {
            return;
        }

        $lines = [];
        $canonical = ($settings['canonicalEnable'] ?? '1') === '1' ? self::canonical($archive, $settings) : '';

        if ($canonical !== '' && !$archive->is('single')) {
            $lines[] = '<link rel="canonical" href="' . self::e($canonical) . '" />';
        }

        $robots = self::robotsMeta($archive, $settings);
        if ($robots !== '') {
            $lines[] = '<meta name="robots" content="' . self::e($robots) . '" />';
        }

        if (($settings['ogEnable'] ?? '1') === '1') {
            $lines = array_merge($lines, self::ogLines($archive, $settings, $canonical));
        }

        if (($settings['timeEnable'] ?? '1') === '1') {
            $script = self::timeFactor($archive);
            if ($script !== '') {
                $lines[] = '<script type="application/ld+json">' . $script . '</script>';
            }
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
            <p><input type="text" class="w-100 text" name="fields[seo_title]" value="<?php echo self::e($title); ?>" placeholder="<?php _e('SEO 标题（留空则沿用内容标题）'); ?>"></p>
            <p><textarea class="w-100" name="fields[seo_desc]" rows="3" placeholder="<?php _e('SEO 描述（留空则自动摘取摘要）'); ?>"><?php echo self::e($desc); ?></textarea></p>
            <p><input type="text" class="w-100 text" name="fields[seo_keys]" value="<?php echo self::e($keys); ?>" placeholder="<?php _e('SEO 关键词，多个用逗号分隔'); ?>"></p>
        </section>
        <section class="typecho-post-option">
            <label class="typecho-label"><?php _e('SEO 技术项'); ?></label>
            <p><input type="url" class="w-100 text mono" name="fields[seo_canonical]" value="<?php echo self::e($canonical); ?>" placeholder="<?php _e('Canonical 覆盖地址（留空则自动生成）'); ?>"></p>
            <p><input type="text" class="w-100 text mono" name="fields[seo_image]" value="<?php echo self::e($image); ?>" placeholder="<?php _e('OG 图片地址（支持相对路径或绝对地址）'); ?>"></p>
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
            $url = (string) ($archive->permalink ?? $archive->archiveUrl ?? $url);
        }
        if ($url === '') {
            $url = (string) ($archive->archiveUrl ?? Settings::siteUrl());
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

    private static function ogLines($archive, array $settings, string $canonical): array
    {
        $title = (string) ($archive->archiveTitle ?? Settings::siteName());
        $desc = trim((string) ($archive->archiveDescription ?? ''));
        if ($desc === '') {
            $desc = self::summary($archive);
        }

        $image = self::image($archive, $settings);
        $type = $archive->is('single') ? 'article' : 'website';
        $url = $canonical !== '' ? $canonical : Settings::siteUrl();

        $lines = [
            '<meta property="og:type" content="' . self::e($type) . '" />',
            '<meta property="og:url" content="' . self::e($url) . '" />',
            '<meta property="og:title" content="' . self::e($title) . '" />',
            '<meta property="og:description" content="' . self::e($desc) . '" />',
            '<meta property="og:site_name" content="' . self::e(Settings::siteName()) . '" />',
            '<meta name="twitter:card" content="' . self::e($image === '' ? 'summary' : 'summary_large_image') . '" />',
            '<meta name="twitter:title" content="' . self::e($title) . '" />',
            '<meta name="twitter:description" content="' . self::e($desc) . '" />',
        ];

        if ($image !== '') {
            $lines[] = '<meta property="og:image" content="' . self::e($image) . '" />';
            $lines[] = '<meta name="twitter:image" content="' . self::e($image) . '" />';
        }

        if ($archive->is('single')) {
            $created = (int) ($archive->created ?? 0);
            $modified = max((int) ($archive->modified ?? 0), $created);
            if ($created > 0) {
                $lines[] = '<meta property="article:published_time" content="' . self::e(date('c', $created)) . '" />';
            }
            if ($modified > 0) {
                $lines[] = '<meta property="article:modified_time" content="' . self::e(date('c', $modified)) . '" />';
            }
        }

        return $lines;
    }

    private static function timeFactor($archive): string
    {
        if ($archive->is('error404')) {
            return '';
        }

        $data = [];
        if ($archive->is('single')) {
            $created = (int) ($archive->created ?? 0);
            $modified = max((int) ($archive->modified ?? 0), $created);
            if ($created > 0) {
                $data['pubDate'] = date('Y-m-d\TH:i:s', $created);
                $data['upDate'] = date('Y-m-d\TH:i:s', $modified);
            }
        } else {
            $updated = (int) ($archive->modified ?? $archive->created ?? 0);
            if ($updated <= 0) {
                $updated = time();
            }
            $data['upDate'] = date('Y-m-d\TH:i:s', $updated);
        }

        return empty($data) ? '' : (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
        if (!empty($archive->archiveDescription)) {
            $summary = (string) $archive->archiveDescription;
        } elseif (isset($archive->excerpt)) {
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

    private static function e(string $value): string
    {
        return Text::e($value);
    }
}
