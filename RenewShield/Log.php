<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Typecho\Db;
use Utils\Schema;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Log
{
    public static function ruleView(string $ruleKey): array
    {
        $ruleKey = trim($ruleKey);
        $catalog = self::ruleCatalog();
        if (isset($catalog[$ruleKey])) {
            return $catalog[$ruleKey] + ['key' => $ruleKey];
        }

        $meta = Rule::metaByKey($ruleKey);
        if ($meta !== null) {
            return [
                'key' => $ruleKey,
                'label' => (string) ($meta['message'] ?? $ruleKey),
                'group' => Rule::groups()[(string) ($meta['group'] ?? '')] ?? '',
                'hint' => '来自基础 WAF 规则库',
            ];
        }

        return [
            'key' => $ruleKey !== '' ? $ruleKey : '-',
            'label' => $ruleKey !== '' ? $ruleKey : '未命名规则',
            'group' => '',
            'hint' => '',
        ];
    }

    public static function payloadSummary(string|array $payload): array
    {
        if (is_array($payload)) {
            $payload = self::payload($payload);
        }

        $text = trim($payload);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        if (!empty($decoded['group'])) {
            $items[] = '分组：' . (Rule::groups()[(string) $decoded['group']] ?? (string) $decoded['group']);
        }
        if (!empty($decoded['target'])) {
            $items[] = '位置：' . (string) $decoded['target'];
        }
        if (!empty($decoded['count']) && !empty($decoded['limit'])) {
            $items[] = '次数：' . (int) $decoded['count'] . '/' . (int) $decoded['limit'];
        }
        if (!empty($decoded['window'])) {
            $items[] = '窗口：' . (int) $decoded['window'] . ' 秒';
        }
        if (!empty($decoded['reason'])) {
            $items[] = '原因：' . (string) $decoded['reason'];
        }
        if (!empty($decoded['ip'])) {
            $items[] = 'IP：' . (string) $decoded['ip'];
        }
        if (!empty($decoded['need'])) {
            $items[] = '权限：' . (string) $decoded['need'];
        }
        if (!empty($decoded['action'])) {
            $items[] = '处理：' . (string) $decoded['action'];
        }
        if (!empty($decoded['risks']) && is_array($decoded['risks'])) {
            foreach (array_slice($decoded['risks'], 0, 2) as $risk) {
                $message = trim((string) ($risk['message'] ?? ''));
                if ($message !== '') {
                    $items[] = $message;
                }
            }
        }

        return array_values(array_unique($items));
    }

    public static function createTables(): void
    {
        try {
            Schema::ensureRenewShield(Db::get());
        } catch (\Throwable $e) {
            error_log('[RenewShield] createTables: ' . $e->getMessage());
        }
    }

    public static function write(
        string $scope,
        string $action,
        string $decision,
        string $ruleKey,
        int $score,
        string $message,
        array $payload = []
    ): void {
        try {
            $request = \Typecho\Request::getInstance();
            $db = Db::get();
            $db->query($db->insert('table.renew_shield_logs')->rows([
                'scope' => Text::cut($scope, 24),
                'action' => Text::cut($action, 24),
                'decision' => Text::cut($decision, 16),
                'rule_key' => Text::cut($ruleKey, 64),
                'score' => $score,
                'method' => Text::cut((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 12),
                'ip' => Text::cut((string) $request->getIp(), 45),
                'path' => Text::cut((string) ($request->getRequestUri() ?? '/'), 1024),
                'ua' => Text::cut((string) $request->getAgent(), 512),
                'message' => Text::cut($message, 255),
                'payload' => self::payload($payload),
                'created_at' => time(),
            ]));
        } catch (\Throwable $e) {
            error_log('[RenewShield] write: ' . $e->getMessage());
        }
    }

    public static function cleanup(int $keepDays): void
    {
        try {
            $before = time() - (max(1, $keepDays) * 86400);
            Db::get()->query(Db::get()->delete('table.renew_shield_logs')->where('created_at < ?', $before));
        } catch (\Throwable $e) {
            self::write('system', 'cleanup', 'observe', 'log.cleanup', 0, $e->getMessage());
        }
    }

    public static function purge(): void
    {
        try {
            Db::get()->query(Db::get()->delete('table.renew_shield_logs'));
        } catch (\Throwable $e) {
            self::write('system', 'purge', 'observe', 'log.purge', 0, $e->getMessage());
        }
    }

    public static function scopeOptions(): array
    {
        return [
            '' => '全部范围',
            'site' => '站点',
            'bot' => 'Bot',
            'spider' => '蜘蛛',
            'challenge' => '挑战',
            'comment' => '评论',
            'login' => '登录',
            'upload' => '上传',
            'waf' => 'WAF',
            'xmlrpc' => 'XML-RPC',
            'access' => '访问控制',
            'system' => '系统',
        ];
    }

    public static function actionOptions(): array
    {
        return [
            '' => '全部动作',
            'allow' => '放行',
            'ban' => '封禁',
            'verify' => '验证',
            'deny' => '拦截',
            'inspect' => '观察',
            'login' => '登录',
            'cleanup' => '清理',
            'purge' => '清空',
            'search' => '检索',
        ];
    }

    public static function decisionOptions(): array
    {
        return [
            '' => '全部决策',
            'allow' => '放行',
            'challenge' => '挑战',
            'block' => '拦截',
            'observe' => '观察',
        ];
    }

    public static function search(array $filters, int $page, int $size): array
    {
        $result = [
            'rows' => [],
            'total' => 0,
            'page' => max(1, $page),
            'size' => max(10, min(200, $size)),
        ];

        try {
            $db = Db::get();
            $select = $db->select()->from('table.renew_shield_logs');
            $count = $db->select(['COUNT(*)' => 'num'])->from('table.renew_shield_logs');

            foreach (['scope', 'decision', 'action'] as $key) {
                $value = trim((string) ($filters[$key] ?? ''));
                if ($value === '') {
                    continue;
                }
                $select->where($key . ' = ?', $value);
                $count->where($key . ' = ?', $value);
            }

            $keyword = trim((string) ($filters['keyword'] ?? ''));
            if ($keyword !== '') {
                $like = '%' . $keyword . '%';
                $select->where('(path LIKE ? OR ip LIKE ? OR ua LIKE ? OR rule_key LIKE ? OR message LIKE ?)', $like, $like, $like, $like, $like);
                $count->where('(path LIKE ? OR ip LIKE ? OR ua LIKE ? OR rule_key LIKE ? OR message LIKE ?)', $like, $like, $like, $like, $like);
            }

            $total = $db->fetchObject($count);
            $result['total'] = (int) ($total->num ?? 0);
            $result['rows'] = $db->fetchAll(
                $select
                    ->order('id', Db::SORT_DESC)
                    ->page($result['page'], $result['size'])
            );
        } catch (\Throwable $e) {
            self::write('system', 'search', 'observe', 'log.search', 0, $e->getMessage());
        }

        return $result;
    }

    public static function insights(int $hours = 24, int $limit = 5): array
    {
        $result = [
            'recent' => 0,
            'blocked' => 0,
            'challenge' => 0,
            'observe' => 0,
            'anomaly' => 0,
            'rules' => [],
            'ips' => [],
            'paths' => [],
            'bans' => [],
        ];

        try {
            $rows = Db::get()->fetchAll(
                Db::get()->select('rule_key', 'decision', 'ip', 'path', 'created_at')
                    ->from('table.renew_shield_logs')
                    ->where('created_at > ?', time() - (max(1, $hours) * 3600))
                    ->order('id', Db::SORT_DESC)
                    ->limit(5000)
            );

            $ruleHits = [];
            $ipHits = [];
            $pathHits = [];
            $banHits = [];

            foreach ($rows as $row) {
                $result['recent']++;

                $decision = (string) ($row['decision'] ?? '');
                if ($decision === 'block') {
                    $result['blocked']++;
                } elseif ($decision === 'challenge') {
                    $result['challenge']++;
                } elseif ($decision === 'observe') {
                    $result['observe']++;
                }

                $rule = trim((string) ($row['rule_key'] ?? ''));
                if ($rule !== '') {
                    $view = self::ruleView($rule);
                    $name = (string) ($view['label'] ?? $rule);
                    $ruleHits[$name] = ($ruleHits[$name] ?? 0) + 1;
                    if ($rule === 'login.anomaly') {
                        $result['anomaly']++;
                    }
                }

                $ip = trim((string) ($row['ip'] ?? ''));
                if ($ip !== '') {
                    $ipHits[$ip] = ($ipHits[$ip] ?? 0) + 1;
                    if (self::isBanRule($rule, $decision)) {
                        $banHits[$ip] = ($banHits[$ip] ?? 0) + 1;
                    }
                }

                $path = trim((string) ($row['path'] ?? ''));
                if ($path !== '') {
                    $pathHits[$path] = ($pathHits[$path] ?? 0) + 1;
                }
            }

            arsort($ruleHits);
            arsort($ipHits);
            arsort($pathHits);

            $result['rules'] = self::top($ruleHits, $limit);
            $result['ips'] = self::top($ipHits, $limit);
            $result['paths'] = self::top($pathHits, $limit);
            $result['bans'] = self::top($banHits, $limit);
        } catch (\Throwable $e) {
            self::write('system', 'search', 'observe', 'log.insights', 0, $e->getMessage());
        }

        return $result;
    }

    public static function legacyPasswordCount(): int
    {
        try {
            $users = Db::get()->fetchAll(
                Db::get()->select('password')
                    ->from('table.users')
                    ->where('group = ?', 'administrator')
            );
        } catch (\Throwable $e) {
            self::write('system', 'search', 'observe', 'log.password', 0, $e->getMessage());
            return 0;
        }

        $count = 0;
        foreach ($users as $user) {
            $hash = (string) ($user['password'] ?? '');
            if ($hash !== '' && \Utils\Password::needsRehash($hash)) {
                $count++;
            }
        }

        return $count;
    }

    private static function payload(array $payload): string
    {
        if ($payload === []) {
            return '';
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '';
        }

        return Text::cut($json, 8000);
    }

    private static function top(array $hits, int $limit): array
    {
        $items = [];
        foreach (array_slice($hits, 0, max(1, $limit), true) as $name => $count) {
            $items[] = [
                'name' => (string) $name,
                'count' => (int) $count,
            ];
        }

        return $items;
    }

    private static function isBanRule(string $rule, string $decision): bool
    {
        if ($rule === 'ip.unban') {
            return false;
        }

        if ($rule === 'ip.ban') {
            return true;
        }

        if ($decision !== 'block') {
            return false;
        }

        return $rule === 'ip.ban'
            || $rule === 'path.trap'
            || str_contains($rule, '.ban')
            || str_contains($rule, 'bad-window');
    }

    private static function ruleCatalog(): array
    {
        return [
            'access.path' => ['label' => '路径访问规则命中', 'group' => '访问控制', 'hint' => '前台路径命中访问控制规则'],
            'access.archive' => ['label' => '归档访问规则命中', 'group' => '访问控制', 'hint' => '文章、页面、分类或标签命中访问规则'],
            'access.rule.invalid' => ['label' => '访问规则写法无效', 'group' => '访问控制', 'hint' => '后台配置中的访问规则存在语法或参数问题'],
            'ua.allow' => ['label' => 'UA 白名单放行', 'group' => 'Bot 识别', 'hint' => '命中 UA 白名单后跳过 Bot 指纹检查'],
            'ua.empty' => ['label' => '空 UA 拦截', 'group' => 'Bot 识别', 'hint' => '请求缺少 User-Agent'],
            'ua.script' => ['label' => '脚本 UA 拦截', 'group' => 'Bot 识别', 'hint' => '命中 curl、wget、python-requests 等脚本特征'],
            'spider.googlebot' => ['label' => 'Google 蜘蛛放行', 'group' => '蜘蛛验证', 'hint' => 'Google 爬虫已通过双向 DNS 验证'],
            'spider.bingbot' => ['label' => 'Bing 蜘蛛放行', 'group' => '蜘蛛验证', 'hint' => 'Bing 爬虫已通过双向 DNS 验证'],
            'spider.baiduspider' => ['label' => '百度蜘蛛放行', 'group' => '蜘蛛验证', 'hint' => '百度爬虫已通过双向 DNS 验证'],
            'path.trap' => ['label' => '扫描陷阱命中', 'group' => '陷阱封禁', 'hint' => '请求访问了明显的扫描器路径'],
            'method.invalid' => ['label' => '异常方法拦截', 'group' => '协议检查', 'hint' => '请求使用了异常或不受支持的方法'],
            'xmlrpc.allowlist' => ['label' => 'XML-RPC 白名单放行', 'group' => 'XML-RPC', 'hint' => 'XML-RPC 来源在白名单内'],
            'rate.limit' => ['label' => '访问频率超限', 'group' => '限频挑战', 'hint' => '命中滑动窗口限频阈值'],
            'proxy.header' => ['label' => '未受信代理头', 'group' => '浏览器识别', 'hint' => '检测到未受信来源携带代理相关请求头'],
            'header.browser' => ['label' => '浏览器头不完整', 'group' => '浏览器识别', 'hint' => '基础浏览器请求头缺失'],
            'sec-fetch.missing' => ['label' => '缺少 Sec-Fetch', 'group' => '浏览器识别', 'hint' => '声称为浏览器但缺少 Sec-Fetch 头'],
            'browser.legacy' => ['label' => '浏览器版本过低', 'group' => '浏览器识别', 'hint' => '声称浏览器的版本低于后台设定的最低值'],
            'http.version' => ['label' => '旧协议浏览器访问', 'group' => '浏览器识别', 'hint' => '声称浏览器访问但协议信息表现为 HTTP/1.x'],
            'challenge.invalid' => ['label' => '挑战令牌无效', 'group' => '挑战验证', 'hint' => '挑战令牌签名无效、过期或被篡改'],
            'challenge.confirm' => ['label' => '挑战确认缺失', 'group' => '挑战验证', 'hint' => '挑战页未完成点击确认即尝试继续访问'],
            'challenge.wait' => ['label' => '挑战等待未结束', 'group' => '挑战验证', 'hint' => '用户过早提交挑战验证'],
            'challenge.missing' => ['label' => '挑战令牌已失效', 'group' => '挑战验证', 'hint' => '挑战令牌已被消费或状态缺失'],
            'challenge.pass' => ['label' => '挑战验证通过', 'group' => '挑战验证', 'hint' => '请求已通过基础挑战，后续短时内可放行'],
            'comment.honeypot' => ['label' => '评论蜜罐命中', 'group' => '评论防护', 'hint' => '评论表单中的隐藏字段被填写'],
            'comment.context' => ['label' => '评论上下文缺失', 'group' => '评论防护', 'hint' => '评论请求缺少有效的页面浏览上下文'],
            'comment.fast' => ['label' => '评论提交过快', 'group' => '评论防护', 'hint' => '评论提交速度低于设定阈值'],
            'comment.links' => ['label' => '评论外链过多', 'group' => '评论防护', 'hint' => '评论中的链接数量超过限制'],
            'comment.challenge' => ['label' => '评论先挑战', 'group' => '评论防护', 'hint' => '评论请求需要先完成基础验证'],
            'upload.double-ext' => ['label' => '双扩展上传拦截', 'group' => '上传防护', 'hint' => '上传文件名包含可疑双扩展'],
            'upload.mime' => ['label' => '上传类型异常', 'group' => '上传防护', 'hint' => '文件扩展名、MIME 或内容特征不一致'],
            'upload.payload' => ['label' => '上传内容可疑', 'group' => '上传防护', 'hint' => '上传样本中检测到脚本或危险片段'],
            'upload.modify.type' => ['label' => '附件替换类型异常', 'group' => '上传防护', 'hint' => '替换附件时类型与原附件不一致'],
            'login.fail' => ['label' => '登录失败记录', 'group' => '登录观察', 'hint' => '记录一次登录失败并累积风险'],
            'login.name.limit' => ['label' => '账号登录失败过多', 'group' => '登录观察', 'hint' => '同一账号在窗口期内累计失败次数过多'],
            'login.success' => ['label' => '登录成功', 'group' => '登录观察', 'hint' => '记录一次成功登录'],
            'login.anomaly' => ['label' => '登录环境变化', 'group' => '登录观察', 'hint' => '同一账号出现新的 IP 或 UA 环境'],
            'ip.ban' => ['label' => 'IP 已被封禁', 'group' => '封禁状态', 'hint' => '来源 IP 已进入临时封禁状态'],
            'ip.unban' => ['label' => 'IP 已解除封禁', 'group' => '封禁状态', 'hint' => '后台手动解除了一条封禁记录'],
            'log.cleanup' => ['label' => '日志清理异常', 'group' => '系统维护', 'hint' => '清理过期日志时出现异常'],
            'log.purge' => ['label' => '日志清空异常', 'group' => '系统维护', 'hint' => '清空日志时出现异常'],
            'log.search' => ['label' => '日志查询异常', 'group' => '系统维护', 'hint' => '后台检索日志时出现异常'],
            'log.insights' => ['label' => '日志洞察异常', 'group' => '系统维护', 'hint' => '生成日志概览时出现异常'],
            'log.password' => ['label' => '密码检查异常', 'group' => '系统维护', 'hint' => '检测旧密码哈希时出现异常'],
        ];
    }
}
