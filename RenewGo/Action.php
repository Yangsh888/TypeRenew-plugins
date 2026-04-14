<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class RenewGo_Action extends Typecho_Widget
{
    private const MAX_JSON_SIZE = 32768;

    public function action()
    {
        $do = (string) $this->request->get('do');

        if ($do === 'jump') {
            $this->jump();
            return;
        }

        $this->guardAdmin();

        switch ($do) {
            case 'test':
                $this->requirePost();
                $this->testRule();
                return;
            case 'purge':
                $this->requirePost();
                $this->purgeLogs();
                return;
            case 'export':
                $this->requirePost();
                $this->exportRules();
                return;
            case 'import':
                $this->requirePost();
                $this->importRules();
                return;
            default:
                $this->jsonError(_t('无效操作'), 400, 'invalid_action');
        }
    }

    public function go()
    {
        $settings = RenewGo_Plugin::getSettings();
        $keepDays = (int) ($settings['logKeepDays'] ?? 0);
        if ($keepDays > 0) {
            $this->maybeCleanupLogs($keepDays);
        }
        $encoded = trim((string) $this->request->get('target', ''));
        ['decoded' => $decoded, 'url' => $url] = $this->resolveTarget($encoded);

        if ($url === '') {
            $this->failPage('go', 'invalid', $decoded, _t('链接无效或已损坏'));
            return;
        }

        if ($settings['mode'] === 'off') {
            $this->failPage('go', 'disabled', $url, _t('外链跳转功能已关闭'));
            return;
        }

        if ($settings['mode'] === 'direct302') {
            $isWhitelisted = RenewGo_Plugin::isWhitelisted($url, $settings);
            if ((string) ($settings['directWhitelistOnly'] ?? '1') === '1') {
                $whitelist = trim((string) ($settings['whitelist'] ?? ''));
                if ($whitelist === '') {
                    $this->failPage('go', 'no-whitelist', $url, _t('直跳模式未配置白名单，请联系管理员'), 200, true);
                    return;
                }
                if (!$isWhitelisted) {
                    $this->failPage('go', 'direct-denied', $url, _t('该外链未在直跳白名单中'), 200, true);
                    return;
                }
            }
            if (!$this->enforceRateLimit($settings, 'go', $url)) {
                return;
            }
            if ($isWhitelisted) {
                RenewGo_Plugin::logEvent('go', 'redirect', $url, (string) $this->request->getReferer(), true);
                $this->response->redirect($url);
                return;
            }
        }

        $jumpUrl = RenewGo_Plugin::buildJumpUrl($encoded);
        $title = (string) ($settings['pageTitle'] ?? _t('外链访问提示'));
        $staySeconds = (int) ($settings['staySeconds'] ?? 0);
        $host = (string) parse_url($url, PHP_URL_HOST);
        $display = mb_strlen($url) > 120 ? mb_substr($url, 0, 117) . '...' : $url;
        $home = $this->siteUrl();
        $newTab = (string) ($settings['openInNewTab'] ?? '1') === '1';
        $template = __DIR__ . '/view/interstitial.php';

        RenewGo_Plugin::logEvent('go', 'interstitial', $url, (string) $this->request->getReferer(), true);

        if (file_exists($template)) {
            include $template;
            return;
        }

        $this->response->redirect($url);
    }

    private function jump(): void
    {
        $settings = RenewGo_Plugin::getSettings();
        $keepDays = (int) ($settings['logKeepDays'] ?? 0);
        if ($keepDays > 0) {
            $this->maybeCleanupLogs($keepDays);
        }

        $encoded = trim((string) $this->request->get('target', ''));
        $time = (int) $this->request->get('ts');
        $sign = (string) $this->request->get('sig');
        if (!RenewGo_Plugin::verifySign($encoded, $time, $sign)) {
            $this->failPage('jump', 'forbidden', '', _t('链接校验失败'), 403, true);
            return;
        }

        ['decoded' => $decoded, 'url' => $url] = $this->resolveTarget($encoded);
        if ($url === '') {
            $this->failPage('jump', 'invalid', $decoded, _t('链接无效或已损坏'), 200, true);
            return;
        }

        if (!$this->enforceRateLimit($settings, 'jump', $url)) {
            return;
        }

        RenewGo_Plugin::logEvent('jump', 'success', $url, (string) $this->request->getReferer(), true);
        $this->response->redirect($url);
    }

    private function testRule(): void
    {
        $raw = $this->rawJson();
        $url = RenewGo_Plugin::normalizeUrl((string) ($raw['url'] ?? ''));
        if ($url === '') {
            $this->jsonError(_t('URL 格式无效'), 400, 'invalid_url');
        }

        $settings = RenewGo_Plugin::getSettings();
        $whitelisted = RenewGo_Plugin::isWhitelisted($url, $settings);
        $rewrite = RenewGo_Plugin::shouldRewriteUrl($url, $settings);
        $this->jsonSuccess([
            'success' => 1,
            'url' => $url,
            'whitelisted' => $whitelisted ? 1 : 0,
            'rewrite' => $rewrite ? 1 : 0,
            'go' => $rewrite ? RenewGo_Plugin::buildGoUrl($url) : $url
        ]);
    }

    private function purgeLogs(): void
    {
        RenewGo_Plugin::purgeLogs();
        $this->jsonSuccess(['success' => 1]);
    }

    private function exportRules(): void
    {
        $data = RenewGo_Plugin::exportRules();
        $this->jsonSuccess(['success' => 1, 'data' => $data]);
    }

    private function importRules(): void
    {
        $raw = $this->rawJson();
        $rules = (string) ($raw['rules'] ?? '');
        $settings = RenewGo_Plugin::importRules($rules);
        $this->jsonSuccess(['success' => 1, 'whitelist' => $settings['whitelist'] ?? '']);
    }

    private function rawJson(): array
    {
        $body = $this->request->getRawBody();
        if (strlen($body) > self::MAX_JSON_SIZE) {
            $this->jsonError(_t('请求体过大'), 413, 'payload_too_large');
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return [];
        }
        return $json;
    }

    private function requirePost(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->jsonError(_t('请求方法不被允许'), 405, 'method_not_allowed');
        }
    }

    private function guardAdmin(): void
    {
        \Widget\User::alloc()->pass('administrator');
        $token = (string) $this->request->get('_');
        $expect = \Widget\Security::alloc()->getToken('renew-go-admin');
        if (!hash_equals($expect, $token)) {
            $this->jsonError(_t('请求校验失败'), 403, 'forbidden');
        }
    }

    private function renderError(string $message): void
    {
        $title = _t('外链访问提示');
        $host = '';
        $display = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $home = $this->siteUrl();
        $jumpUrl = $home;
        $staySeconds = 0;
        $newTab = false;
        include __DIR__ . '/view/interstitial.php';
    }

    private function siteUrl(): string
    {
        $site = '';
        if (isset($this->options) && is_object($this->options) && !empty($this->options->siteUrl)) {
            $site = (string) $this->options->siteUrl;
        }
        if ($site === '') {
            try {
                $site = (string) \Utils\Helper::options()->siteUrl;
            } catch (Throwable $e) {
                $site = '';
            }
        }
        if ($site === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $site = $scheme . $host . '/';
        }
        return rtrim($site, '/') . '/';
    }

    private function resolveTarget(string $encoded): array
    {
        $decoded = RenewGo_Plugin::decodeTarget($encoded);

        return [
            'decoded' => $decoded,
            'url' => RenewGo_Plugin::normalizeUrl($decoded)
        ];
    }

    private function enforceRateLimit(array $settings, string $scope, string $url): bool
    {
        $ip = (string) $this->request->getIp();
        if (RenewGo_Plugin::checkRateLimit($ip, $settings)) {
            return true;
        }

        $this->failPage($scope, 'rate-limit', $url, _t('访问过于频繁，请稍后重试'), 429, true);
        return false;
    }

    private function failPage(
        string $scope,
        string $result,
        string $target,
        string $message,
        int $status = 200,
        bool $force = false
    ): void {
        RenewGo_Plugin::logEvent($scope, $result, $target, (string) $this->request->getReferer(), $force);
        if ($status !== 200) {
            $this->response->setStatus($status);
        }
        $this->renderError($message);
    }

    private function jsonError(string $message, int $status, string $code): void
    {
        $this->response->throwJson([
            'success' => 0,
            'error' => $message,
            'code' => $code
        ], $status);
    }

    private function jsonSuccess(array $data): void
    {
        if (!isset($data['success'])) {
            $data['success'] = 1;
        }
        $this->response->throwJson($data);
    }

    private function maybeCleanupLogs(int $keepDays): void
    {
        $cache = \Typecho\Cache::getInstance();
        $cacheKey = 'renewgo:last_cleanup';
        $now = time();
        $interval = 3600;

        if ($cache->enabled()) {
            $hit = false;
            $lastCleanup = (int) $cache->get($cacheKey, $hit);
            if ($hit && ($now - $lastCleanup) < $interval) {
                return;
            }
            $cache->set($cacheKey, $now, $interval * 2);
        }

        try {
            RenewGo_Plugin::cleanupLogs($keepDays);
        } catch (\Throwable $e) {
            error_log('[RenewGo] cleanupLogs: ' . $e->getMessage());
        }
    }
}
