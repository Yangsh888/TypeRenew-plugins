<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Typecho\Request;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Context
{
    public array $headers = [];
    public string $ip = '';
    public string $ua = '';
    public string $method = 'GET';
    public string $uri = '/';
    public string $path = '/';
    public string $query = '';
    public string $body = '';
    public string $protocol = 'HTTP/1.1';
    public string $referer = '';
    public bool $isAjax = false;
    public bool $isJson = false;
    public bool $isLogin = false;
    public bool $isComment = false;
    public bool $isXmlRpc = false;
    public bool $isUpload = false;
    public bool $isShieldAction = false;
    public bool $claimsBrowser = false;

    public static function fromRequest(?Request $request = null): self
    {
        $request ??= Request::getInstance();
        $self = new self();
        $self->headers = self::headers();
        $self->ip = (string) $request->getIp();
        $self->ua = (string) $request->getAgent();
        $self->method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $self->uri = (string) ($request->getRequestUri() ?? '/');
        $self->path = (string) (parse_url($self->uri, PHP_URL_PATH) ?? '/');
        $self->query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $self->body = $request->getRawBody();
        $self->protocol = (string) ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
        $self->referer = (string) $request->getReferer();
        $self->isAjax = $request->isAjax();
        $self->isJson = stripos((string) ($self->headers['content-type'] ?? ''), 'application/json') !== false;
        $self->isLogin = self::matchPath($self->path, ['/action/login']);
        $self->isComment = self::isCommentPath($self->path);
        $self->isXmlRpc = self::matchPath($self->path, ['/action/xmlrpc']);
        $self->isUpload = self::matchPath($self->path, ['/action/upload']);
        $self->isShieldAction = self::matchPath($self->path, ['/action/renew-shield']);
        $self->claimsBrowser = self::claimsBrowser($self->ua);
        return $self;
    }

    public function routeScope(): string
    {
        return match (true) {
            $this->isLogin => 'login',
            $this->isComment => 'comment',
            $this->isXmlRpc => 'xmlrpc',
            $this->isUpload => 'upload',
            default => 'site',
        };
    }

    public function browserName(): string
    {
        $ua = $this->ua;
        return match (true) {
            stripos($ua, 'Edg/') !== false => 'edge',
            stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false => 'chrome',
            stripos($ua, 'Firefox/') !== false => 'firefox',
            stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome/') === false => 'safari',
            default => '',
        };
    }

    public function browserVersion(): int
    {
        $ua = $this->ua;
        foreach ([
            'edge' => '/Edg\/(\d+)/i',
            'chrome' => '/Chrome\/(\d+)/i',
            'firefox' => '/Firefox\/(\d+)/i',
            'safari' => '/Version\/(\d+)/i',
        ] as $name => $pattern) {
            if ($this->browserName() === $name && preg_match($pattern, $ua, $matches)) {
                return (int) ($matches[1] ?? 0);
            }
        }

        return 0;
    }

    public function isPageRequest(): bool
    {
        if ($this->isXmlRpc || $this->isUpload || $this->isShieldAction) {
            return false;
        }

        if (preg_match('/\.(?:css|js|mjs|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|mp4|mp3|webm|pdf|txt|xml)$/i', $this->path) === 1) {
            return false;
        }

        $accept = strtolower((string) ($this->headers['accept'] ?? ''));
        if ($accept === '') {
            return !$this->isAjax && !$this->isJson;
        }

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml+xml');
    }

    private static function headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if ($contentType !== '') {
            $headers['content-type'] = $contentType;
        }

        $contentLength = (string) ($_SERVER['CONTENT_LENGTH'] ?? '');
        if ($contentLength !== '') {
            $headers['content-length'] = $contentLength;
        }

        return $headers;
    }

    private static function claimsBrowser(string $ua): bool
    {
        return preg_match('/mozilla\/5\.0|chrome\/|safari\/|firefox\/|edg\//i', $ua) === 1;
    }

    private static function matchPath(string $path, array $targets): bool
    {
        foreach ($targets as $target) {
            if ($path === $target) {
                return true;
            }
        }
        return false;
    }

    private static function isCommentPath(string $path): bool
    {
        return preg_match('#/(comment|trackback)(/page/\d+)?/?$#i', $path) === 1;
    }
}
