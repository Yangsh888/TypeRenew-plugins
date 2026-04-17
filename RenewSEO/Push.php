<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

use Typecho\Http\Client;
use Typecho\Response;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Push
{
    private static array $queue = [
        'urls' => [],
        'deleted' => [],
        'rebuild' => false,
        'reasons' => [],
    ];

    private static bool $registered = false;

    public static function schedule(array $urls, array $deleted = [], bool $rebuild = true, string $reason = 'content'): void
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '1') !== '1') {
            return;
        }

        foreach (self::normalizeItems($urls, false) as $item) {
            self::$queue['urls'][$item['url']] = $item;
        }
        foreach (self::normalizeItems($deleted, true) as $item) {
            self::$queue['deleted'][$item['url']] = $item;
        }
        self::$queue['rebuild'] = self::$queue['rebuild'] || $rebuild;
        self::$queue['reasons'][$reason] = true;

        if (self::$registered) {
            return;
        }

        self::$registered = true;
        Response::getInstance()->addResponder(static function (): void {
            $task = self::task();
            if (empty($task['urls']) && empty($task['deleted']) && !$task['rebuild']) {
                return;
            }

            $settings = Settings::load();
            if (($settings['pushAsync'] ?? '1') !== '1') {
                self::process($task);
                return;
            }

            $payload = [
                'ts' => time(),
                'task' => $task,
            ];
            $payload['token'] = self::makeAsyncToken((int) $payload['ts']);

            try {
                $client = Client::get();
                if (!$client) {
                    throw new \RuntimeException('http client unavailable');
                }
                $client->setTimeout((int) ($settings['pushTimeout'] ?? 10));
                $client->setMethod('POST');
                $client->setHeader('Content-Type', 'application/json; charset=utf-8');
                $client->setJson($payload);
                $client->send(Settings::actionUrl('async'));
                if ((int) $client->getResponseStatus() >= 400) {
                    throw new \RuntimeException('async responder status ' . $client->getResponseStatus());
                }
            } catch (\Throwable $e) {
                Log::write('push', 'async', 'error', '', $e->getMessage());
                self::process($task);
            }
        });
    }

    public static function process(array $task): array
    {
        $settings = Settings::load();
        Log::maybeCleanup($settings);

        $task = [
            'urls' => array_values(self::normalizeItems($task['urls'] ?? [], false)),
            'deleted' => array_values(self::normalizeItems($task['deleted'] ?? [], true)),
            'rebuild' => !empty($task['rebuild']),
            'reason' => (string) ($task['reason'] ?? 'async'),
        ];

        $result = [
            'rebuild' => false,
            'baidu' => null,
            'indexnow' => null,
            'bing' => null,
        ];

        if ($task['rebuild']) {
            $result['rebuild'] = Files::sync($task['reason'], true)['ok'] ?? false;
        }

        $result['baidu'] = self::pushBaidu($task['urls'], $settings);
        $result['indexnow'] = self::pushIndexNow(array_merge($task['urls'], $task['deleted']), $settings);
        $result['bing'] = self::pushBing($task['urls'], $settings);

        return $result;
    }

    public static function manualUrl(string $url): array
    {
        return self::process([
            'urls' => [[
                'url' => Settings::absoluteUrl($url),
                'created' => time(),
                'modified' => time(),
                'deleted' => false,
            ]],
            'deleted' => [],
            'rebuild' => false,
            'reason' => 'manual',
        ]);
    }

    public static function makeAsyncToken(int $ts): string
    {
        $secret = (string) (Settings::options()->secret ?? Settings::siteHost());
        return hash_hmac('sha256', 'renewseo|' . $ts, $secret);
    }

    public static function verifyAsyncToken(int $ts, string $token): bool
    {
        if ($ts <= 0 || abs(time() - $ts) > 300) {
            return false;
        }
        return hash_equals(self::makeAsyncToken($ts), $token);
    }

    private static function task(): array
    {
        return [
            'urls' => array_values(self::$queue['urls']),
            'deleted' => array_values(self::$queue['deleted']),
            'rebuild' => self::$queue['rebuild'],
            'reason' => implode(',', array_keys(self::$queue['reasons'])),
        ];
    }

    private static function normalizeItems(array $items, bool $deleted): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $item = ['url' => $item];
            }
            $url = Settings::absoluteUrl((string) ($item['url'] ?? ''));
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            if ((string) (parse_url($url, PHP_URL_HOST) ?? '') !== Settings::siteHost()) {
                continue;
            }
            $normalized[$url] = [
                'url' => $url,
                'created' => (int) ($item['created'] ?? 0),
                'modified' => (int) ($item['modified'] ?? time()),
                'deleted' => $deleted || !empty($item['deleted']),
            ];
        }
        return $normalized;
    }

    private static function pushBaidu(array $items, array $settings): ?array
    {
        if (($settings['baiduEnable'] ?? '0') !== '1' || empty($settings['baiduToken'])) {
            return null;
        }

        $days = (int) ($settings['baiduDays'] ?? 0);
        $urls = [];
        foreach ($items as $item) {
            if (!empty($item['deleted'])) {
                continue;
            }
            if ($days > 0 && !empty($item['created']) && (time() - (int) $item['created']) > ($days * 86400)) {
                continue;
            }
            $urls[] = $item['url'];
        }

        if (empty($urls)) {
            return ['ok' => true, 'skipped' => true];
        }

        $site = rtrim(Settings::siteUrl(), '/');
        $token = (string) $settings['baiduToken'];

        $result = self::sendBaiduBatch($urls, $site, $token, ($settings['baiduQuick'] ?? '0') === '1');
        if (($settings['baiduQuick'] ?? '0') === '1' && !$result['ok']) {
            $fallback = self::sendBaiduBatch($urls, $site, $token, false);
            $result['fallback'] = $fallback;
            if ($fallback['ok']) {
                $result['ok'] = true;
            }
        }

        Log::write('push', 'baidu', $result['ok'] ? 'info' : 'error', '', '百度推送执行完成', $result);
        return $result;
    }

    private static function sendBaiduBatch(array $urls, string $site, string $token, bool $quick): array
    {
        $endpoint = 'https://data.zz.baidu.com/urls?site=' . rawurlencode($site) . '&token=' . rawurlencode($token);
        if ($quick) {
            $endpoint .= '&type=daily';
        }

        $responses = [];
        $ok = true;
        foreach (array_chunk($urls, 20) as $chunk) {
            $body = implode("\n", $chunk);
            $response = self::request($endpoint, $body, ['Content-Type: text/plain']);
            $decoded = json_decode((string) ($response['body'] ?? ''), true);
            $response['data'] = is_array($decoded) ? $decoded : [];
            if (($response['status'] ?? 500) >= 400 || isset($response['data']['error'])) {
                $ok = false;
            }
            $responses[] = $response;
        }

        return [
            'ok' => $ok,
            'quick' => $quick,
            'count' => count($urls),
            'responses' => $responses,
        ];
    }

    private static function pushIndexNow(array $items, array $settings): ?array
    {
        if (($settings['indexNowEnable'] ?? '0') !== '1' || empty($settings['indexNowKey'])) {
            return null;
        }

        $urls = array_values(array_unique(array_column($items, 'url')));
        if (empty($urls)) {
            return ['ok' => true, 'skipped' => true];
        }

        $payload = [
            'host' => Settings::siteHost(),
            'key' => (string) $settings['indexNowKey'],
            'urlList' => $urls,
        ];

        $response = self::request(
            'https://www.bing.com/indexnow',
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type: application/json; charset=utf-8']
        );
        $ok = ($response['status'] ?? 500) < 400;
        $result = [
            'ok' => $ok,
            'count' => count($urls),
            'response' => $response,
        ];
        Log::write('push', 'indexnow', $ok ? 'info' : 'error', '', 'IndexNow 推送执行完成', $result);
        return $result;
    }

    private static function pushBing(array $items, array $settings): ?array
    {
        if (($settings['bingEnable'] ?? '0') !== '1' || empty($settings['bingApiKey'])) {
            return null;
        }

        $urls = array_values(array_unique(array_column($items, 'url')));
        if (empty($urls)) {
            return ['ok' => true, 'skipped' => true];
        }

        $endpoint = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey='
            . rawurlencode((string) $settings['bingApiKey']);
        $responses = [];
        $ok = true;

        foreach (array_chunk($urls, 500) as $chunk) {
            $payload = [
                'siteUrl' => rtrim(Settings::siteUrl(), '/'),
                'urlList' => array_values($chunk),
            ];
            $response = self::request(
                $endpoint,
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ['Content-Type: application/json; charset=utf-8']
            );

            if (($response['status'] ?? 500) >= 400) {
                $ok = false;
            }
            $responses[] = $response;
        }

        $result = [
            'ok' => $ok,
            'count' => count($urls),
            'responses' => $responses,
        ];
        Log::write('push', 'bing', $ok ? 'info' : 'error', '', 'Bing 推送执行完成', $result);
        return $result;
    }

    private static function request(string $url, string $body, array $headers = []): array
    {
        $settings = Settings::load();
        $timeout = (int) ($settings['pushTimeout'] ?? 10);
        try {
            $client = Client::get();
            if ($client) {
                $client->setTimeout($timeout);
                foreach ($headers as $header) {
                    [$name, $value] = array_pad(explode(':', $header, 2), 2, '');
                    $client->setHeader(trim($name), trim($value));
                }
                $client->setData($body, Client::METHOD_POST);
                $client->send($url);
                return [
                    'status' => (int) $client->getResponseStatus(),
                    'body' => (string) $client->getResponseBody(),
                ];
            }
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => $e->getMessage(),
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        $status = 500;
        if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return [
            'status' => $status,
            'body' => is_string($responseBody) ? $responseBody : '',
        ];
    }
}
