<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

use Typecho\Db;
use Utils\Schema;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Log
{
    private const TARGET_LIMIT = 512;
    private const MESSAGE_LIMIT = 255;
    private const PAYLOAD_LIMIT = 8000;
    private const PATH_LIMIT = 512;
    private const URL_LIMIT = 1024;
    private const REFERER_LIMIT = 1024;
    private const UA_LIMIT = 512;

    public static function createTables(): void
    {
        try {
            Schema::ensureRenewSeo(Db::get());
        } catch (\Throwable $e) {
            self::write('system', 'createTables', 'error', '', $e->getMessage());
        }
    }

    public static function write(
        string $channel,
        string $action,
        string $level,
        string $target,
        string $message,
        array $payload = []
    ): void {
        try {
            $db = Db::get();
            $data = [
                'channel' => Text::slice(trim($channel), 24),
                'action' => Text::slice(trim($action), 32),
                'level' => Text::slice(trim($level), 16),
                'target' => self::cut($target, self::TARGET_LIMIT),
                'message' => self::cut($message, self::MESSAGE_LIMIT),
                'payload' => self::encodePayload($payload),
                'created_at' => time(),
            ];

            $db->query($db->insert('table.renew_seo_logs')->rows($data));
        } catch (\Throwable $e) {
            self::report('write failed [' . $channel . '/' . $action . ']: ' . $e->getMessage());
        }
    }

    public static function record404($request): void
    {
        try {
            $settings = Settings::load();
            self::maybeCleanup($settings);

            $fullUrl = (string) $request->getRequestUrl();
            if ($fullUrl === '') {
                return;
            }

            $path = self::normalizePath($fullUrl, (string) $request->getRequestUri());
            $hash = sha1(strtolower($path));
            $db = Db::get();
            $row = $db->fetchRow(
                $db->select('id', 'hits')
                    ->from('table.renew_seo_404')
                    ->where('path_hash = ?', $hash)
                    ->limit(1)
            );

            $now = time();
            $referer = self::cut((string) $request->getReferer(), self::REFERER_LIMIT);
            $ip = self::cut((string) $request->getIp(), 45);
            $ua = self::cut((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), self::UA_LIMIT);

            if ($row) {
                $db->query(
                    $db->update('table.renew_seo_404')->rows([
                        'path' => self::cut($path, self::PATH_LIMIT),
                        'full_url' => self::cut($fullUrl, self::URL_LIMIT),
                        'referer' => $referer,
                        'ip' => $ip,
                        'ua' => $ua,
                        'hits' => (int) ($row['hits'] ?? 0) + 1,
                        'last_seen' => $now,
                    ])->where('id = ?', (int) $row['id'])
                );
                return;
            }

            $db->query($db->insert('table.renew_seo_404')->rows([
                'path_hash' => $hash,
                'path' => self::cut($path, self::PATH_LIMIT),
                'full_url' => self::cut($fullUrl, self::URL_LIMIT),
                'referer' => $referer,
                'ip' => $ip,
                'ua' => $ua,
                'hits' => 1,
                'first_seen' => $now,
                'last_seen' => $now,
            ]));
        } catch (\Throwable $e) {
            self::write('404', 'record', 'error', '', $e->getMessage());
        }
    }

    public static function maybeCleanup(array $settings): void
    {
        if (($settings['logAutoClean'] ?? '1') !== '1') {
            return;
        }

        $cache = Settings::cache();
        $key = 'renewseo:last_cleanup';
        $now = time();
        if ($cache->enabled()) {
            $hit = false;
            $last = (int) $cache->get($key, $hit);
            if ($hit && ($now - $last) < 3600) {
                return;
            }
            $cache->set($key, $now, 7200);
        }

        self::cleanup((int) ($settings['logKeepDays'] ?? 30), (int) ($settings['notFoundKeepDays'] ?? 30));
    }

    public static function cleanup(int $logKeepDays, int $notFoundKeepDays): void
    {
        try {
            $db = Db::get();
            if ($logKeepDays > 0) {
                $before = time() - ($logKeepDays * 86400);
                $db->query($db->delete('table.renew_seo_logs')->where('created_at < ?', $before));
            }
            if ($notFoundKeepDays > 0) {
                $before = time() - ($notFoundKeepDays * 86400);
                $db->query($db->delete('table.renew_seo_404')->where('last_seen < ?', $before));
            }
        } catch (\Throwable $e) {
            self::write('system', 'cleanup', 'error', '', $e->getMessage());
        }
    }

    public static function purgeLogs(): void
    {
        try {
            Db::get()->query(Db::get()->delete('table.renew_seo_logs'));
        } catch (\Throwable $e) {
            self::write('system', 'purgeLogs', 'error', '', $e->getMessage());
        }
    }

    public static function purge404(): void
    {
        try {
            Db::get()->query(Db::get()->delete('table.renew_seo_404'));
        } catch (\Throwable $e) {
            self::write('system', 'purge404', 'error', '', $e->getMessage());
        }
    }

    public static function recentLogs(int $limit): array
    {
        try {
            $db = Db::get();
            return $db->fetchAll(
                $db->select()->from('table.renew_seo_logs')
                    ->order('id', Db::SORT_DESC)
                    ->limit(max(1, min(200, $limit)))
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function recent404(int $limit): array
    {
        try {
            $db = Db::get();
            return $db->fetchAll(
                $db->select()->from('table.renew_seo_404')
                    ->order('last_seen', Db::SORT_DESC)
                    ->limit(max(1, min(200, $limit)))
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function summary(): array
    {
        $summary = [
            'logs' => 0,
            'errors' => 0,
            'notFound' => 0,
            'top404' => 0,
        ];

        try {
            $db = Db::get();
            $total = $db->fetchObject($db->select(['COUNT(*)' => 'num'])->from('table.renew_seo_logs'));
            $errors = $db->fetchObject(
                $db->select(['COUNT(*)' => 'num'])->from('table.renew_seo_logs')->where('level = ?', 'error')
            );
            $notFound = $db->fetchObject($db->select(['COUNT(*)' => 'num'])->from('table.renew_seo_404'));
            $top404 = $db->fetchObject(
                $db->select(['MAX(hits)' => 'num'])->from('table.renew_seo_404')
            );

            $summary['logs'] = (int) ($total->num ?? 0);
            $summary['errors'] = (int) ($errors->num ?? 0);
            $summary['notFound'] = (int) ($notFound->num ?? 0);
            $summary['top404'] = (int) ($top404->num ?? 0);
        } catch (\Throwable $e) {
            self::report('write failed: ' . $e->getMessage());
        }

        return $summary;
    }

    private static function encodePayload(array $payload): string
    {
        if (empty($payload)) {
            return '';
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '';
        }

        return self::cut($json, self::PAYLOAD_LIMIT);
    }

    private static function cut(string $value, int $max): string
    {
        return Text::slice(trim($value), $max);
    }

    private static function normalizePath(string $fullUrl, string $fallback): string
    {
        $path = (string) parse_url($fullUrl, PHP_URL_PATH);
        if ($path === '') {
            $path = (string) parse_url($fallback, PHP_URL_PATH);
        }
        if ($path === '') {
            $path = '/';
        }
        return $path;
    }

    private static function report(string $message): void
    {
        error_log('[RenewSEO] ' . $message);
    }
}
