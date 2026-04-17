<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Rule
{
    public static function metaByKey(string $key): ?array
    {
        foreach (self::waf() as $rule) {
            if ((string) ($rule['key'] ?? '') === $key) {
                return $rule;
            }
        }

        return null;
    }

    public static function waf(): array
    {
        return [
            [
                'key' => 'sql.union',
                'group' => 'sql',
                'score' => 92,
                'message' => '检测到 SQL 注入特征',
                'pattern' => '/union(?:\s+all)?\s+select|information_schema|load_file\(|into\s+outfile|updatexml\(|extractvalue\(/i',
                'targets' => ['path', 'query', 'body'],
            ],
            [
                'key' => 'sql.blind',
                'group' => 'sql',
                'score' => 88,
                'message' => '检测到盲注特征',
                'pattern' => '/(?:sleep|benchmark|pg_sleep|waitfor\s+delay)\s*\(|(?:and|or)\s+\d+\s*=\s*\d+/i',
                'targets' => ['query', 'body'],
            ],
            [
                'key' => 'xss.script',
                'group' => 'xss',
                'score' => 86,
                'message' => '检测到 XSS 特征',
                'pattern' => '/<script\b|<\/script>|javascript:|data:text\/html|vbscript:/i',
                'targets' => ['path', 'query', 'body', 'referer'],
            ],
            [
                'key' => 'xss.event',
                'group' => 'xss',
                'score' => 82,
                'message' => '检测到事件注入特征',
                'pattern' => '/on(?:error|load|click|mouseover|focus|submit)\s*=|srcdoc\s*=|<iframe\b/i',
                'targets' => ['query', 'body'],
            ],
            [
                'key' => 'cmd.exec',
                'group' => 'cmd',
                'score' => 92,
                'message' => '检测到命令注入特征',
                'pattern' => '/(?:;|\|\||&&|`)\s*(?:cat|bash|sh|curl|wget|powershell|cmd|nc|python|perl|php)\b/i',
                'targets' => ['query', 'body'],
            ],
            [
                'key' => 'tpl.ssti',
                'group' => 'tpl',
                'score' => 80,
                'message' => '检测到模板注入特征',
                'pattern' => '/\{\{[\s\S]{0,160}\}\}|\$\{[\s\S]{0,160}\}|<#[\s\S]{0,160}#>/i',
                'targets' => ['query', 'body'],
            ],
            [
                'key' => 'nosql.op',
                'group' => 'nosql',
                'score' => 78,
                'message' => '检测到 NoSQL 注入特征',
                'pattern' => '/(?:^|[^a-z])\$(?:ne|gt|gte|lt|lte|nin|regex|where)\b/i',
                'targets' => ['query', 'body'],
            ],
            [
                'key' => 'path.traversal',
                'group' => 'path',
                'score' => 95,
                'message' => '检测到路径穿越特征',
                'pattern' => '/\.\.\/|\.\.\\\\|%2e%2e%2f|%2e%2e%5c/i',
                'targets' => ['path', 'query', 'body'],
            ],
            [
                'key' => 'file.include',
                'group' => 'include',
                'score' => 94,
                'message' => '检测到文件包含特征',
                'pattern' => '/(?:php|data|zip|phar|expect|glob|file):\/\/|(?:^|[?&])(template|file|path|include)=/i',
                'targets' => ['path', 'query', 'body'],
            ],
            [
                'key' => 'xxe.entity',
                'group' => 'xxe',
                'score' => 94,
                'message' => '检测到 XXE 特征',
                'pattern' => '/<!doctype|<!entity|system\s+"(?:file|http|php):\/\/|public\s+"-\/\/w3c/i',
                'targets' => ['body'],
            ],
            [
                'key' => 'php.object',
                'group' => 'serialize',
                'score' => 86,
                'message' => '检测到反序列化特征',
                'pattern' => '/(?:^|[;{])O:\d+:"|(?:^|[;{])C:\d+:"|a:\d+:\{.*s:\d+:/i',
                'targets' => ['body', 'query'],
            ],
            [
                'key' => 'ssrf.local',
                'group' => 'ssrf',
                'score' => 86,
                'message' => '检测到 SSRF 特征',
                'pattern' => '/https?:\/\/(?:127\.0\.0\.1|0\.0\.0\.0|localhost|169\.254\.169\.254|10\.|172\.(?:1[6-9]|2\d|3[01])\.|192\.168\.)/i',
                'targets' => ['query', 'body'],
            ],
            [
                'key' => 'crlf.inject',
                'group' => 'protocol',
                'score' => 82,
                'message' => '检测到 CRLF 注入特征',
                'pattern' => '/%0d%0a|\r\n/i',
                'targets' => ['path', 'query', 'body', 'referer'],
            ],
            [
                'key' => 'proto.smuggle',
                'group' => 'protocol',
                'score' => 90,
                'message' => '检测到疑似请求走私特征',
                'pattern' => '/transfer-encoding:\s*chunked[\s\S]*content-length:|content-length:[^\n]*,[^\n]*/i',
                'targets' => ['headers'],
            ],
            [
                'key' => 'proto.host',
                'group' => 'protocol',
                'score' => 70,
                'message' => '检测到异常 Host 协议特征',
                'pattern' => '/(?:^|\n)host:\s*(?:0\.0\.0\.0|127\.0\.0\.1|localhost)(?:\n|$)/i',
                'targets' => ['headers'],
            ],
            [
                'key' => 'cve.known',
                'group' => 'cve',
                'score' => 95,
                'message' => '检测到已知漏洞（CVE）探测特征',
                'pattern' => '/(?:jndi:(?:ldap|rmi|dns)|(?:\$|%24)(?:\{|%7b)jndi:)|thinkphp\/library\/think|invokefunction|call_user_func_array|(?:OgnlContext|ognl\.Ognl|java\.lang\.Runtime|java\.lang\.ProcessBuilder)|_ignition\/execute-solution|wp-json\/wp\/v2\/users/i',
                'targets' => ['path', 'query', 'body', 'headers'],
            ],
        ];
    }

    public static function groups(): array
    {
        return [
            'sql' => 'SQL 注入',
            'xss' => 'XSS',
            'cmd' => '命令注入',
            'tpl' => '模板注入',
            'nosql' => 'NoSQL 注入',
            'path' => '路径穿越',
            'include' => '文件包含',
            'xxe' => 'XXE',
            'serialize' => '反序列化',
            'ssrf' => 'SSRF',
            'protocol' => '协议异常',
            'cve' => '漏洞探测',
        ];
    }
}
