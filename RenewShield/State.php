<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class State
{
    private const PREFIX = 'renewshield:state:';
    private const NAMESPACE_OPTION = 'renewShieldStateNs';
    private static ?string $runtimeNamespace = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $cache = Settings::cache();
        $stateKey = self::scope($key);
        if ($cache->enabled()) {
            $hit = false;
            $value = $cache->get(self::PREFIX . $stateKey, $hit);
            return $hit ? $value : $default;
        }

        try {
            $row = Db::get()->fetchRow(
                Db::get()->select('value', 'expires_at')
                    ->from('table.renew_shield_state')
                    ->where('name_hash = ?', sha1($stateKey))
                    ->limit(1)
            );
            if (!$row) {
                return $default;
            }
            if ((int) ($row['expires_at'] ?? 0) > 0 && (int) $row['expires_at'] < time()) {
                self::delete($key);
                return $default;
            }

            $decoded = json_decode((string) ($row['value'] ?? ''), true);
            return $decoded ?? $default;
        } catch (\Throwable $e) {
            Log::write('system', 'get', 'observe', 'state.read', 0, $e->getMessage());
            return $default;
        }
    }

    public static function set(string $key, mixed $value, int $ttl = 0): void
    {
        $cache = Settings::cache();
        $stateKey = self::scope($key);
        if ($cache->enabled()) {
            $cache->set(self::PREFIX . $stateKey, $value, $ttl > 0 ? $ttl : null);
            return;
        }

        try {
            $db = Db::get();
            $now = time();
            $expires = $ttl > 0 ? $now + $ttl : 0;
            $hash = sha1($stateKey);
            $rows = [
                'name_hash' => $hash,
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'expires_at' => $expires,
            ];

            $row = $db->fetchRow(
                $db->select('id')->from('table.renew_shield_state')
                    ->where('name_hash = ?', $hash)
                    ->limit(1)
            );

            if ($row) {
                $db->query($db->update('table.renew_shield_state')->rows($rows)->where('id = ?', (int) $row['id']));
                return;
            }

            try {
                $db->query($db->insert('table.renew_shield_state')->rows($rows));
            } catch (\Throwable $e) {
                $updated = (int) $db->query(
                    $db->update('table.renew_shield_state')->rows($rows)->where('name_hash = ?', $hash)
                );
                if ($updated > 0) {
                    return;
                }
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::write('system', 'set', 'observe', 'state.write', 0, $e->getMessage());
        }
    }

    public static function delete(string $key): void
    {
        $cache = Settings::cache();
        $stateKey = self::scope($key);
        if ($cache->enabled()) {
            $cache->delete(self::PREFIX . $stateKey);
            return;
        }

        try {
            Db::get()->query(Db::get()->delete('table.renew_shield_state')->where('name_hash = ?', sha1($stateKey)));
        } catch (\Throwable $e) {
            Log::write('system', 'delete', 'observe', 'state.delete', 0, $e->getMessage());
        }
    }

    public static function reset(): void
    {
        try {
            self::storeNamespace(self::newNamespace());
        } catch (\Throwable $e) {
            Log::write('system', 'reset', 'observe', 'state.namespace', 0, $e->getMessage());
        }

        try {
            Db::get()->query(Db::get()->delete('table.renew_shield_state'));
        } catch (\Throwable $e) {
            Log::write('system', 'reset', 'observe', 'state.reset', 0, $e->getMessage());
        }
    }

    public static function hit(string $key, int $window, int $increment = 1): int
    {
        return self::withLock($key, static function () use ($key, $window, $increment): int {
            $cache = Settings::cache();
            $stateKey = self::scope($key);
            if ($cache->enabled()) {
                $hit = false;
                $current = (int) $cache->get(self::PREFIX . $stateKey, $hit);
                $next = ($hit ? $current : 0) + max(1, $increment);
                $cache->set(self::PREFIX . $stateKey, $next, max(1, $window));
                return $next;
            }

            $current = (int) self::get($key, 0);
            $next = $current + max(1, $increment);
            self::set($key, $next, $window);
            return $next;
        });
    }

    public static function cleanup(): void
    {
        $cache = Settings::cache();
        if ($cache->enabled()) {
            return;
        }

        try {
            Db::get()->query(
                Db::get()->delete('table.renew_shield_state')
                    ->where('expires_at > ? AND expires_at < ?', 0, time())
            );
        } catch (\Throwable $e) {
            Log::write('system', 'cleanup', 'observe', 'state.cleanup', 0, $e->getMessage());
        }
    }

    private static function withLock(string $key, callable $callback): mixed
    {
        $dir = sys_get_temp_dir();
        if (!is_string($dir) || $dir === '' || !is_dir($dir)) {
            return $callback();
        }

        $path = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . 'renewshield-' . sha1($key) . '.lock';
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return $callback();
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return $callback();
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function scope(string $key): string
    {
        return self::namespace() . ':' . $key;
    }

    private static function namespace(): string
    {
        if (is_string(self::$runtimeNamespace) && self::$runtimeNamespace !== '') {
            return self::$runtimeNamespace;
        }

        try {
            $row = Db::get()->fetchRow(
                Db::get()->select('value')
                    ->from('table.options')
                    ->where('name = ?', self::NAMESPACE_OPTION)
                    ->where('user = ?', 0)
                    ->limit(1)
            );
            $value = trim((string) ($row['value'] ?? ''));
            if ($value !== '') {
                self::$runtimeNamespace = $value;
                return self::$runtimeNamespace;
            }
        } catch (\Throwable $e) {
            Log::write('system', 'namespace', 'observe', 'state.namespace.read', 0, $e->getMessage());
        }

        self::$runtimeNamespace = 'v1';
        return self::$runtimeNamespace;
    }

    private static function storeNamespace(string $namespace): void
    {
        $db = Db::get();
        $affected = $db->query(
            $db->update('table.options')->rows(['value' => $namespace])
                ->where('name = ?', self::NAMESPACE_OPTION)
                ->where('user = ?', 0)
        );

        if ($affected === 0) {
            $db->query(
                $db->insert('table.options')->rows([
                    'name' => self::NAMESPACE_OPTION,
                    'value' => $namespace,
                    'user' => 0,
                ])
            );
        }

        self::$runtimeNamespace = $namespace;
    }

    private static function newNamespace(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return sha1(uniqid('', true));
        }
    }
}
