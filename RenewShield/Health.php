<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Health
{
    public static function inspect(array $settings): array
    {
        $items = [];
        $options = Helper::options();
        $insights = Log::insights(7 * 24, 5);

        if (file_exists(Settings::rootPath('install.php'))) {
            $items[] = self::item('warn', '安装入口', '检测到根目录仍存在 "install.php"。如安装流程已结束，建议将该文件权限收紧为不可写，并优先采用 400、440、600 或 640 这类仅站点运行账户可读的权限；具体以主机权限模型为准。如条件允许，再配合服务器规则阻止外部访问。');
        }

        $ruleIssues = Access::issues($settings);
        if ($ruleIssues !== []) {
            $items[] = self::item(
                'warn',
                '访问规则',
                '检测到无效规则：' . implode('；', array_slice($ruleIssues, 0, 3))
            );
        }

        $accessRedirect = trim((string) ($settings['accessRedirect'] ?? ''));
        if (preg_match('#^https?://#i', $accessRedirect) === 1) {
            $items[] = self::item(
                'warn',
                '访问跳转',
                '当前访问规则默认跳转地址是外部链接。该能力可用于业务跳转，但也会放大开放跳转风险，建议仅指向自有站点或站内相对路径。'
            );
        }

        $accessHtml = (string) ($settings['accessHtml'] ?? '');
        if ($accessHtml !== '' && preg_match('/<script\b|javascript:|onload\s*=|onerror\s*=/i', $accessHtml) === 1) {
            $items[] = self::item(
                'warn',
                '访问文案',
                '访问规则自定义 HTML 中包含脚本或事件属性。建议仅保留静态说明和链接，避免引入主动脚本。'
            );
        }

        if ((int) ($options->allowXmlRpc ?? 0) > 0) {
            $items[] = self::item(
                'warn',
                'XML-RPC',
                '站点已启用 XML-RPC；如业务不涉及相关内容，建议在系统设置中关闭。'
            );
        }

        if ((int) ($options->commentsAntiSpam ?? 1) !== 1) {
            $items[] = self::item('warn', '评论反垃圾', '核心评论反垃圾已关闭，建议同时启用。');
        }

        if (empty($options->allowedAttachmentTypes ?? [])) {
            $items[] = self::item('warn', '上传类型', '未检测到附件类型白名单，建议补充核心附件类型设置。');
        }

        if (Log::legacyPasswordCount() > 0) {
            $items[] = self::item('warn', '密码存储', '检测到管理员密码哈希仍需升级，建议相关管理员重新登录或修改密码，以完成新哈希重算。');
        }

        $uploadPath = Settings::rootPath('usr/uploads');
        if (!is_dir($uploadPath)) {
            $items[] = self::item('error', '上传目录', '未检测到 "usr/uploads" 目录，附件上传能力将无法正常工作。');
        } elseif (!is_writable($uploadPath)) {
            $items[] = self::item('warn', '上传目录', '"usr/uploads" 当前不可写，附件上传或替换可能失败。');
        }

        $configPath = Settings::rootPath('config.inc.php');
        if (file_exists($configPath) && is_writable($configPath)) {
            $items[] = self::item('warn', '配置文件', '"config.inc.php" 当前可写。建议改为不可写，并优先采用 400、440、600 或 640 这类仅站点运行账户可读的权限；具体以主机权限模型为准，避免继续保持可写。');
        }

        $dangerous = [];
        foreach ([
            '.env',
            '.git/config',
            'vendor/phpunit',
            'backup.zip',
            'backup.sql',
            'dump.sql',
            'config.php.bak',
            'config.inc.php.bak',
        ] as $relative) {
            if (file_exists(Settings::rootPath($relative))) {
                $dangerous[] = $relative;
            }
        }

        if ($dangerous !== []) {
            $items[] = self::item('warn', '敏感路径', '检测到以下路径：' . implode('、', $dangerous) . '。请确认未公开访问。');
        }

        if (($settings['httpVersionCheck'] ?? '0') === '1') {
            $items[] = self::item('warn', 'HTTP 版本识别', '该检查依赖运行环境提供的协议信息，在 CDN、反向代理、共享主机场景下可能出现误判。');
        }

        if (($settings['blockProxy'] ?? '0') === '1') {
            $message = '开启后可能影响 CDN、负载均衡或企业网络访问。';
            if (trim((string) ($settings['proxyTrusted'] ?? '')) === '') {
                $message .= ' 当前未配置受信代理 IP，误判风险较高。';
            } else {
                $message .= ' 当前已配置受信代理 IP，请确认列表完整。';
            }

            $items[] = self::item('warn', '代理头识别', $message);
        }

        if (($settings['secFetchCheck'] ?? '0') === '1') {
            $items[] = self::item('warn', 'Sec-Fetch 校验', '该检查只基于请求头存在性做轻量判断，旧浏览器、应用内打开或特殊代理链路下可能出现误判。');
        }

        if (($settings['headerCompleteness'] ?? '0') === '1') {
            $items[] = self::item('warn', '浏览器头完整度', '当前仅检查 Accept、Accept-Language、Accept-Encoding 三项基础头，建议结合日志观察后再长期启用。');
        }

        if (($settings['wafMode'] ?? 'balanced') === 'observe' && ($settings['riskMode'] ?? 'challenge') === 'observe') {
            $items[] = self::item('warn', '防护模式', '当前 WAF 与风险识别均处于仅观察模式，主要用于记录和调试，不会主动拦截。');
        }

        if (
            ($settings['denyEmptyUa'] ?? '1') !== '1'
            && ($settings['blockScriptUa'] ?? '1') !== '1'
            && ($settings['wafEnable'] ?? '1') !== '1'
            && (int) ($settings['generalLimit'] ?? 0) <= 0
        ) {
            $items[] = self::item('warn', '基础防护', '当前核心基础防护项目大多处于关闭状态，插件只能提供非常有限的观察能力。');
        }

        if (
            ($settings['uploadDoubleExt'] ?? '1') !== '1'
            && ($settings['uploadScan'] ?? '1') !== '1'
            && (int) ($settings['uploadMaxKb'] ?? 0) <= 0
        ) {
            $items[] = self::item('warn', '上传保护', '当前未启用双扩展检查、内容扫描和大小限制，上传保护能力接近关闭。');
        }

        if (trim((string) ($settings['trapPaths'] ?? '')) === '') {
            $items[] = self::item('warn', '扫描陷阱', '当前未配置任何陷阱路径，常见扫描器命中后将无法直接识别与封禁。');
        }

        if (strlen((string) ($settings['signKey'] ?? '')) < 32) {
            $items[] = self::item('warn', '签名密钥', '挑战验证签名密钥长度偏短，建议重新保存插件配置以刷新密钥。');
        }

        if (($insights['anomaly'] ?? 0) > 0) {
            $items[] = self::item('warn', '登录环境变化', '最近 7 天记录到 ' . (int) $insights['anomaly'] . ' 次新的登录环境，请确认是否存在共享账号或异常登录。');
        }

        if ($items === []) {
            $items[] = self::item('ok', '检查通过', '当前未发现需要处理的问题。');
        }

        return $items;
    }

    private static function item(string $level, string $title, string $message): array
    {
        return [
            'level' => $level,
            'title' => $title,
            'message' => $message,
        ];
    }
}
