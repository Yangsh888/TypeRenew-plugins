<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Date;
use Typecho\Request;
use Typecho\Response;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Guard
{
    private const PASS_COOKIE = '__renewshield_pass';
    private const COMMENT_COOKIE = '__renewshield_comment';
    private const FIELD_HP = 'renewshield_hp';
    private const FIELD_COMMENT_TOKEN = 'renewshield_ctx';
    private const WAF_QUERY_BYTES = 8192;
    private const WAF_BODY_BYTES = 16384;
    private const UPLOAD_SAMPLE_BYTES = 8192;
    private const BAD_WINDOW = 1800;
    private const CHALLENGE_TTL = 1800;
    private const PASS_TTL = 3600;
    private const COMMENT_TTL = 43200;

    public static function boot(): void
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1') {
            return;
        }

        self::maybeCleanup($settings);
        $context = Context::fromRequest();

        if ($context->isShieldAction) {
            return;
        }

        if (\Widget\User::alloc()->pass('administrator', true)) {
            return;
        }

        $decision = Access::matchPath($context, $settings);
        if ($decision !== null) {
            self::denyAccess($decision, 'access.path');
        }

        if (self::ipAllowed($context->ip, (string) ($settings['ipAllowlist'] ?? ''))) {
            return;
        }

        if (self::ipAllowed($context->ip, (string) ($settings['ipDenylist'] ?? ''))) {
            self::block('site', 'ip.deny', 100, '当前 IP 在拒绝名单中');
        }

        if (self::isBanned($context->ip)) {
            self::block('site', 'ip.ban', 100, 'IP 已被临时封禁');
        }

        $uaAllowed = self::uaAllowed($context->ua, (string) ($settings['uaAllowlist'] ?? ''));
        if ($uaAllowed) {
            Log::write('bot', 'allow', 'allow', 'ua.allow', 0, '命中 UA 白名单，跳过 Bot 指纹检查');
        } elseif (($settings['allowSpiders'] ?? '1') === '1') {
            $spider = Spider::detect($context->ua);
            if ($spider !== '' && Spider::verify($spider, $context->ip, (int) ($settings['spiderCacheHours'] ?? 24))) {
                Log::write('spider', 'verify', 'allow', 'spider.' . $spider, 0, '搜索引擎已双向验证放行');
                return;
            }
        }

        if (self::trapHit($context->path, (string) ($settings['trapPaths'] ?? ''))) {
            self::ban($context->ip, (int) ($settings['trapBanHours'] ?? 72) * 3600, 'trap');
            self::block('waf', 'path.trap', 100, '访问了陷阱路径');
        }

        if (
            ($settings['denyBadMethods'] ?? '1') === '1'
            && !in_array($context->method, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)
        ) {
            self::block('waf', 'method.invalid', 95, '请求方法不被允许');
        }

        if ($context->isXmlRpc && self::ipAllowed($context->ip, (string) ($settings['xmlrpcAllowlist'] ?? ''))) {
            Log::write('xmlrpc', 'allow', 'allow', 'xmlrpc.allowlist', 0, 'XML-RPC 请求命中白名单');
            return;
        }

        if (!$uaAllowed && ($settings['denyEmptyUa'] ?? '1') === '1' && trim($context->ua) === '') {
            self::markBad($context, $settings);
            self::block('bot', 'ua.empty', 85, '空 User-Agent 请求已拦截');
        }

        if (
            !$uaAllowed
            && ($settings['blockScriptUa'] ?? '1') === '1'
            && self::isScriptUa($context->ua, (string) ($settings['uaDenylist'] ?? ''))
        ) {
            self::markBad($context, $settings);
            self::block('bot', 'ua.script', 90, '当前 User-Agent 已被识别为脚本请求');
        }

        if (
            ($settings['commentRequireChallenge'] ?? '0') === '1'
            && $context->isComment
            && $context->method === 'POST'
            && !\Widget\User::alloc()->hasLogin()
            && !self::hasPass($context)
            && self::commentNeedsChallenge($context)
        ) {
            self::challenge($context, $settings, 'comment.challenge', 60, '评论请求需要先完成基础验证');
        }

        $loginLock = self::loginFailureLock($context, $settings);
        if ($loginLock !== null) {
            self::markBad($context, $settings);
            self::block('login', 'login.name.limit', 85, (string) ($loginLock['message'] ?? '当前账号的登录失败次数过多，请稍后再试'), $loginLock);
        }

        if (!$uaAllowed) {
            $rate = self::rateLimit($context, $settings);
            if ($rate !== null) {
                if (($rate['mode'] ?? 'challenge') === 'block') {
                    self::markBad($context, $settings);
                    self::block($context->routeScope(), 'rate.limit', 90, (string) $rate['message'], $rate);
                }

                self::challenge($context, $settings, 'rate.limit', 70, (string) $rate['message'], $rate);
            }
        }

        if (($settings['wafEnable'] ?? '1') === '1') {
            $waf = self::waf($context);
            if ($waf !== null) {
                self::handleWaf($context, $settings, $waf);
            }
        }

        $risks = $uaAllowed ? [] : self::risks($context, $settings);
        if ($risks !== []) {
            self::handleRisks($context, $settings, $risks);
        }
    }

    public static function archive(object $archive): void
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1') {
            return;
        }

        $decision = Access::matchArchive($archive, $settings);
        if ($decision !== null && !\Widget\User::alloc()->pass('administrator', true)) {
            self::denyAccess($decision, 'access.archive');
        }
    }

    public static function header(string $_header, object $_archive): void
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1') {
            return;
        }

        echo '<style>.renewshield-hp{position:absolute!important;left:-9999px!important;top:-9999px!important;opacity:0!important;pointer-events:none!important;}</style>';
    }

    public static function footer(object $archive): void
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1' || !$archive->is('single')) {
            return;
        }

        $minSeconds = (int) ($settings['commentMinSeconds'] ?? 4);
        $token = '';
        $tokenField = self::FIELD_COMMENT_TOKEN;
        if ($minSeconds > 0) {
            $context = Context::fromRequest(Request::getInstance());
            $token = self::makeToken('comment', $context, self::COMMENT_TTL, []);
            Cookie::set(self::COMMENT_COOKIE, $token, time() + self::COMMENT_TTL);
            State::set(self::commentViewKey($context, (int) ($archive->cid ?? 0)), time(), self::COMMENT_TTL);
        }

        $hp = self::FIELD_HP;
        $tokenJson = Common::jsonEncode($token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, '""');
        echo <<<HTML
<script>
(function () {
    function inject(form) {
        if (!form || form.dataset.renewshieldReady === '1') {
            return;
        }
        form.dataset.renewshieldReady = '1';

        var hp = document.createElement('input');
        hp.type = 'text';
        hp.name = '{$hp}';
        hp.autocomplete = 'off';
        hp.className = 'renewshield-hp';
        hp.tabIndex = -1;

        form.appendChild(hp);
        if ({$tokenJson} !== '') {
            var ctx = document.createElement('input');
            ctx.type = 'hidden';
            ctx.name = '{$tokenField}';
            ctx.value = {$tokenJson};
            form.appendChild(ctx);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('#comments form, form[action*="/comment"], form[action*="/trackback"]');
        for (var i = 0; i < forms.length; i++) {
            inject(forms[i]);
        }
    });
})();
</script>
HTML;
    }

    public static function comment(array $comment, object $_content): array
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1') {
            return $comment;
        }

        $request = Request::getInstance();
        $context = Context::fromRequest($request);

        $hp = trim((string) $request->get(self::FIELD_HP, ''));
        if ($hp !== '') {
            self::markBad($context, $settings);
            self::denyComment('comment.honeypot', '评论请求未通过安全校验');
        }

        $minSeconds = (int) ($settings['commentMinSeconds'] ?? 4);
        if ($minSeconds > 0) {
            $token = (string) $request->get(self::FIELD_COMMENT_TOKEN, '');
            if ($token === '') {
                $token = (string) Cookie::get(self::COMMENT_COOKIE, '');
            }
            $issuedAt = 0;
            if ($token !== '') {
                $payload = self::readToken($token, 'comment', $context);
                if ($payload === null) {
                    self::denyComment('comment.context', '评论请求缺少有效页面上下文，请刷新页面后重试');
                }
                $issuedAt = (int) ($payload['iat'] ?? 0);
                if ($issuedAt <= 0) {
                    self::denyComment('comment.context', '评论请求缺少有效页面上下文，请刷新页面后重试');
                }
            } else {
                $issuedAt = self::commentViewAt($context, (int) ($_content->cid ?? 0));
                if ($issuedAt <= 0) {
                    self::denyComment('comment.context', '评论请求缺少有效页面上下文，请刷新页面后重试');
                }
            }

            if ((time() - $issuedAt) < $minSeconds) {
                self::markBad($context, $settings);
                self::denyComment('comment.fast', '评论提交过快，请稍后再试');
            }
        }

        $links = preg_match_all('#https?://#i', (string) ($comment['text'] ?? ''), $matches);
        if ((int) $links > (int) ($settings['commentLinks'] ?? 3)) {
            self::denyComment('comment.links', '评论外链数量过多', ['links' => (int) $links]);
        }

        $context->body = (string) ($comment['text'] ?? '');
        $waf = self::waf($context);
        if ($waf !== null) {
            self::markBad($context, $settings);
            self::denyComment((string) $waf['rule'], '评论内容触发安全规则，已被拦截', $waf);
        }

        return $comment;
    }

    public static function loginSucceed(object $_user, string $name, string $_password, bool $_temporarily, int $_expire): void
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1') {
            return;
        }

        $context = Context::fromRequest();
        State::delete(self::loginPairKey($context->ip, $name));
        State::delete(self::loginNameKey($name));
        $lastKey = 'login:last:' . sha1(strtolower($name));
        $last = State::get($lastKey, []);

        if (is_array($last) && !empty($last['ip']) && ($last['ip'] !== $context->ip || ($last['ua'] ?? '') !== $context->ua)) {
            Log::write('login', 'login', 'observe', 'login.anomaly', 25, '检测到新的登录环境', [
                'name' => $name,
                'last_ip' => $last['ip'],
                'current_ip' => $context->ip,
            ]);
        }

        State::set($lastKey, ['ip' => $context->ip, 'ua' => $context->ua, 'time' => time()], 86400 * 365);
        Log::write('login', 'login', 'allow', 'login.success', 0, '登录成功');
    }

    public static function loginFail(object $_user, string $name, string $_password, bool $_temporarily, int $_expire): void
    {
        $context = Context::fromRequest();
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1') {
            return;
        }

        $window = (int) ($settings['loginWindow'] ?? 900);
        State::hit(self::loginPairKey($context->ip, $name), $window);
        State::hit(self::loginNameKey($name), $window);
        self::markBad($context, $settings);
        Log::write('login', 'login', 'observe', 'login.fail', 20, '登录失败', ['name' => $name]);
    }

    public static function uploadHandle(array $file): array
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1') {
            return self::coreUpload($file);
        }

        self::inspectUpload($file, $settings, true);
        return self::coreUpload($file);
    }

    public static function modifyHandle(array $content, array $file): array
    {
        $settings = Settings::load();
        if (($settings['enabled'] ?? '0') !== '1') {
            return self::coreModify($content, $file);
        }

        $name = (string) ($file['name'] ?? '');
        $ext = self::uploadExt($name);
        if ($ext === '' || $ext !== (string) ($content['attachment']->type ?? '')) {
            self::denyUpload('upload.modify.type', _t('上传文件类型与原附件不一致'), [
                'name' => $name,
                'expect' => (string) ($content['attachment']->type ?? ''),
                'actual' => $ext,
            ]);
        }

        self::inspectUpload($file, $settings, false, 'upload.modify');
        return self::coreModify($content, $file);
    }

    public static function verifyChallengeToken(string $token): array
    {
        $context = Context::fromRequest();
        $payload = self::readToken($token, 'challenge', $context, false);
        if ($payload === null) {
            Log::write('challenge', 'verify', 'block', 'challenge.invalid', 30, '挑战令牌无效或已过期');
            return ['ok' => false, 'message' => '验证令牌无效或已过期'];
        }

        $wait = (int) ($payload['wait'] ?? 0);
        if (time() < (int) ($payload['iat'] ?? 0) + $wait) {
            Log::write('challenge', 'verify', 'block', 'challenge.wait', 20, '挑战倒计时尚未结束');
            return ['ok' => false, 'message' => '请等待校验倒计时结束'];
        }

        $nonce = trim((string) ($payload['nonce'] ?? ''));
        if ($nonce === '' || State::get('challenge:' . $nonce, null) === null) {
            Log::write('challenge', 'verify', 'block', 'challenge.missing', 20, '挑战令牌已被消费或不存在');
            return ['ok' => false, 'message' => '验证令牌已失效，请重新获取'];
        }
        State::delete('challenge:' . $nonce);

        Cookie::set(
            self::PASS_COOKIE,
            self::makeToken('pass', $context, self::PASS_TTL, ['ctx' => (string) ($payload['ctx'] ?? '')]),
            time() + self::PASS_TTL
        );
        Log::write('challenge', 'verify', 'allow', 'challenge.pass', 0, '挑战验证通过');

        return [
            'ok' => true,
            'redirect' => self::safeReturn((string) ($payload['return'] ?? '/')),
        ];
    }

    public static function clearPass(): void
    {
        Cookie::delete(self::PASS_COOKIE);
    }

    private static function rateLimit(Context $context, array $settings): ?array
    {
        $scope = $context->routeScope();
        $window = match ($scope) {
            'login' => (int) ($settings['loginWindow'] ?? 900),
            'comment' => (int) ($settings['commentWindow'] ?? 300),
            'xmlrpc' => (int) ($settings['xmlrpcWindow'] ?? 600),
            default => (int) ($settings['generalWindow'] ?? 60),
        };
        $limit = match ($scope) {
            'login' => (int) ($settings['loginLimit'] ?? 5),
            'comment' => (int) ($settings['commentLimit'] ?? 6),
            'xmlrpc' => (int) ($settings['xmlrpcLimit'] ?? 10),
            default => (int) ($settings['generalLimit'] ?? 120),
        };

        $key = 'rate:' . $scope . ':' . sha1($context->ip);
        $count = State::hit($key, $window);
        if ($count <= $limit) {
            return null;
        }

        return [
            'count' => $count,
            'limit' => $limit,
            'window' => $window,
            'mode' => in_array($scope, ['login', 'xmlrpc'], true) ? 'block' : 'challenge',
            'message' => '访问频率过高，请稍后再试',
        ];
    }

    private static function loginFailureLock(Context $context, array $settings): ?array
    {
        if (!$context->isLogin || $context->method !== 'POST') {
            return null;
        }

        $name = trim((string) Request::getInstance()->get('name', ''));
        if ($name === '') {
            return null;
        }

        $window = (int) ($settings['loginWindow'] ?? 900);
        $limit = (int) ($settings['loginLimit'] ?? 5);
        $count = max(
            (int) State::get(self::loginPairKey($context->ip, $name), 0),
            (int) State::get(self::loginNameKey($name), 0)
        );
        if ($count < $limit) {
            return null;
        }

        return [
            'count' => $count,
            'limit' => $limit,
            'window' => $window,
            'mode' => 'block',
            'name' => $name,
            'message' => '当前账号的登录失败次数过多，请稍后再试',
        ];
    }

    private static function waf(Context $context): ?array
    {
        $sources = [
            'path' => Text::cut($context->path, self::WAF_QUERY_BYTES),
            'query' => Text::cut($context->query, self::WAF_QUERY_BYTES),
            'body' => Text::cut($context->body, self::WAF_BODY_BYTES),
            'referer' => Text::cut($context->referer, self::WAF_QUERY_BYTES),
            'headers' => Text::cut(self::headerDump($context), self::WAF_QUERY_BYTES),
        ];

        foreach (Rule::waf() as $rule) {
            foreach ((array) ($rule['targets'] ?? []) as $target) {
                $haystack = (string) ($sources[$target] ?? '');
                if ($haystack === '') {
                    continue;
                }

                if (preg_match((string) ($rule['pattern'] ?? ''), $haystack) === 1) {
                    return [
                        'rule' => (string) ($rule['key'] ?? 'waf.rule'),
                        'group' => (string) ($rule['group'] ?? ''),
                        'score' => (int) ($rule['score'] ?? 0),
                        'message' => (string) ($rule['message'] ?? '检测到异常请求'),
                        'target' => (string) $target,
                    ];
                }
            }
        }

        return null;
    }

    private static function risks(Context $context, array $settings): array
    {
        $risks = [];

        if (($settings['blockProxy'] ?? '0') === '1' && self::hasUntrustedProxyHeaders($context, $settings)) {
            $risks[] = ['rule' => 'proxy.header', 'score' => 60, 'message' => '检测到未受信的代理相关请求头'];
        }

        if (self::hasSmugglingSignals($context)) {
            $risks[] = ['rule' => 'proto.smuggle', 'score' => 85, 'message' => '检测到请求头冲突或疑似走私特征'];
        }

        if (
            ($settings['headerCompleteness'] ?? '1') === '1'
            && $context->claimsBrowser
            && $context->isPageRequest()
            && !self::headersComplete($context)
        ) {
            $risks[] = ['rule' => 'header.browser', 'score' => 60, 'message' => '浏览器基础请求头完整度异常'];
        }

        if (
            ($settings['secFetchCheck'] ?? '1') === '1'
            && $context->claimsBrowser
            && $context->isPageRequest()
            && !isset($context->headers['sec-fetch-site'])
        ) {
            $risks[] = ['rule' => 'sec-fetch.missing', 'score' => 55, 'message' => '浏览器声称请求缺少 Sec-Fetch 头'];
        }

        if (($settings['browserCheck'] ?? '1') === '1' && $context->isPageRequest()) {
            $browser = $context->browserName();
            $version = $context->browserVersion();
            $min = self::browserMinVersion($browser, $settings);
            if ($browser !== '' && $version > 0 && $min > 0 && $version < $min) {
                $risks[] = ['rule' => 'browser.legacy', 'score' => 60, 'message' => '浏览器版本低于最低要求'];
            }
        }

        if (
            ($settings['httpVersionCheck'] ?? '0') === '1'
            && $context->claimsBrowser
            && $context->isPageRequest()
            && stripos($context->protocol, 'HTTP/1.') !== false
            && !$context->isLogin
        ) {
            $risks[] = ['rule' => 'http.version', 'score' => 45, 'message' => '浏览器声称访问但协议版本较旧'];
        }

        return $risks;
    }

    private static function handleWaf(Context $context, array $settings, array $waf): void
    {
        $mode = (string) ($settings['wafMode'] ?? 'balanced');
        $score = (int) ($waf['score'] ?? 0);
        $rule = (string) ($waf['rule'] ?? 'waf.rule');
        $message = (string) ($waf['message'] ?? '检测到异常请求');

        if ($mode === 'observe') {
            self::observe('waf', $rule, $score, $message, $waf);
            return;
        }

        self::markBad($context, $settings);

        if ($mode === 'balanced' && $score < 85) {
            if (self::hasPass($context)) {
                self::observe('waf', $rule, $score, '请求已通过基础验证，当前规则仅记录观察', $waf);
                return;
            }

            self::challenge($context, $settings, $rule, $score, $message, $waf);
        }

        self::block('waf', $rule, $score, $message, $waf);
    }

    private static function handleRisks(Context $context, array $settings, array $risks): void
    {
        $top = $risks[0];
        $mode = (string) ($settings['riskMode'] ?? 'challenge');
        $rule = (string) ($top['rule'] ?? 'risk.rule');
        $score = (int) ($top['score'] ?? 0);
        $message = (string) ($top['message'] ?? '检测到可疑请求');
        $payload = ['risks' => $risks];

        if ($mode === 'observe') {
            self::observe($context->routeScope(), $rule, $score, $message, $payload);
            return;
        }

        if ($mode === 'block') {
            self::block($context->routeScope(), $rule, $score, $message, $payload);
        } elseif (self::hasPass($context)) {
            self::observe($context->routeScope(), $rule, $score, '请求已通过基础验证，当前风险仅记录观察', $payload);
            return;
        }

        self::challenge($context, $settings, $rule, $score, $message, $payload);
    }

    private static function block(string $scope, string $rule, int $score, string $message, array $payload = []): void
    {
        Log::write($scope, 'deny', 'block', $rule, $score, $message, $payload);
        Page::block('请求已被拦截', $message, Log::payloadSummary($payload));
    }

    private static function challenge(Context $context, array $settings, string $rule, int $score, string $message, array $payload = []): void
    {
        Log::write($context->routeScope(), 'verify', 'challenge', $rule, $score, $message, $payload);

        $nonce = bin2hex(random_bytes(12));
        State::set('challenge:' . $nonce, ['rule' => $rule, 'path' => $context->path], self::CHALLENGE_TTL);

        $token = self::makeToken('challenge', $context, self::CHALLENGE_TTL, [
            'return' => self::safeReturn($context->uri),
            'wait' => (int) ($settings['challengeWait'] ?? 3),
            'nonce' => $nonce,
        ]);

        Page::challenge(
            '请先完成基础验证',
            $message,
            Settings::actionUrl('challenge'),
            $token,
            (int) ($settings['challengeWait'] ?? 3),
            Log::payloadSummary($payload)
        );
    }

    private static function observe(string $scope, string $rule, int $score, string $message, array $payload = []): void
    {
        Log::write($scope, 'inspect', 'observe', $rule, $score, $message, $payload);
    }

    private static function denyComment(string $rule, string $message, array $payload = []): void
    {
        Log::write('comment', 'deny', 'block', $rule, 70, $message, $payload);
        throw new \Typecho\Exception($message);
    }

    private static function denyUpload(string $rule, string $message, array $payload = []): void
    {
        Log::write('upload', 'deny', 'block', $rule, 80, $message, $payload);
        throw new \RuntimeException($message);
    }

    private static function denyAccess(array $decision, string $rule): void
    {
        Log::write('access', 'deny', 'block', $rule, 90, '受限资源访问被拦截', [
            'action' => $decision['action'] ?? 'html',
            'need' => $decision['need'] ?? 'login',
        ]);

        if (($decision['action'] ?? 'html') === 'redirect' && !empty($decision['redirect'])) {
            $target = self::safeRedirect((string) $decision['redirect']);
            Response::getInstance()->setStatus(302)->setHeader('Location', $target)->sendHeaders();
            exit;
        }

        if (($decision['action'] ?? 'html') === '403') {
            Page::block('禁止访问', '你当前没有访问该页面的权限。');
        }

        Page::access('访问受限', (string) ($decision['html'] ?? ''), self::safeRedirect((string) ($decision['redirect'] ?? '')));
    }

    private static function makeToken(string $type, Context $context, int $ttl, array $extra): string
    {
        $ctx = (string) ($extra['ctx'] ?? '');
        if ($ctx === '') {
            $ctx = self::contextFingerprint($context);
        }
        unset($extra['ctx']);

        $payload = array_merge($extra, [
            'type' => $type,
            'ip' => sha1($context->ip),
            'ctx' => $ctx,
            'iat' => time(),
            'exp' => time() + max(60, $ttl),
        ]);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $body = rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
        $sign = hash_hmac('sha256', $body, (string) (Settings::load()['signKey'] ?? ''));
        return $body . '.' . $sign;
    }

    private static function readToken(string $token, string $type, Context $context, bool $matchContext = true): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$body, $sign] = $parts;
        $expect = hash_hmac('sha256', $body, (string) (Settings::load()['signKey'] ?? ''));
        if (!hash_equals($expect, $sign)) {
            return null;
        }

        $json = base64_decode(strtr($body, '-_', '+/') . str_repeat('=', (4 - strlen($body) % 4) % 4), true);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($payload)) {
            return null;
        }

        if (($payload['type'] ?? '') !== $type || (int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        if (($payload['ip'] ?? '') !== sha1($context->ip)) {
            return null;
        }

        if ($matchContext && ($payload['ctx'] ?? '') !== self::contextFingerprint($context)) {
            return null;
        }

        return $payload;
    }

    private static function hasPass(Context $context): bool
    {
        $token = (string) Cookie::get(self::PASS_COOKIE);
        if ($token === '') {
            return false;
        }

        return self::readToken($token, 'pass', $context) !== null;
    }

    private static function contextFingerprint(Context $context): string
    {
        $path = strtolower(trim((string) ($context->path ?? '')));
        $prefix = $path;
        if ($prefix !== '' && str_contains($prefix, '/')) {
            $segments = array_values(array_filter(explode('/', trim($prefix, '/')), 'strlen'));
            $prefix = isset($segments[0]) ? '/' . $segments[0] : '/';
        }

        return sha1(strtolower($context->method . '|' . $context->routeScope() . '|' . $prefix));
    }

    private static function commentViewAt(Context $context, int $contentId): int
    {
        if ($contentId <= 0) {
            return 0;
        }

        return (int) State::get(self::commentViewKey($context, $contentId), 0);
    }

    private static function commentViewKey(Context $context, int $contentId): string
    {
        return 'comment:view:' . sha1(strtolower($context->ip . '|' . $context->ua . '|' . $contentId));
    }

    private static function markBad(Context $context, ?array $settings = null): void
    {
        $settings ??= Settings::load();
        $count = State::hit('bad:' . sha1($context->ip), self::BAD_WINDOW);
        if ($count >= (int) ($settings['badLimit'] ?? 8)) {
            self::ban($context->ip, (int) ($settings['autoBanHours'] ?? 24) * 3600, 'bad-window');
        }
    }

    private static function headerDump(Context $context): string
    {
        $lines = [];
        foreach ($context->headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\n", $lines);
    }

    private static function uploadExt(string $name): string
    {
        $name = str_replace(['"', '<', '>'], '', $name);
        $name = str_replace('\\', '/', $name);
        $name = false === strpos($name, '/') ? ('a' . $name) : str_replace('/', '/a', $name);
        $info = pathinfo($name);
        return isset($info['extension']) ? strtolower((string) $info['extension']) : '';
    }

    private static function uploadSample(array $file): string
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp !== '' && is_file($tmp) && is_readable($tmp)) {
            $data = file_get_contents($tmp, false, null, 0, self::UPLOAD_SAMPLE_BYTES);
            return is_string($data) ? $data : '';
        }

        foreach (['bytes', 'bits'] as $key) {
            if (isset($file[$key]) && is_string($file[$key])) {
                return substr($file[$key], 0, self::UPLOAD_SAMPLE_BYTES);
            }
        }

        return '';
    }

    private static function uploadPayloadRisk(string $name, string $sample): bool
    {
        $ext = self::uploadExt($name);
        if ($ext !== '' && preg_match('/^(php|phtml|phar|jsp|asp|aspx|cgi|sh|bat|cmd|exe)$/i', $ext)) {
            return true;
        }

        if ($ext === 'svg') {
            return preg_match('/<script\b|onload\s*=|onerror\s*=|javascript:|foreignobject/i', $sample) === 1;
        }

        return preg_match('/<\?(?:php|=)|<script\b|eval\(|base64_decode\(|powershell|cmd\.exe/i', $sample) === 1;
    }

    private static function inspectUpload(array $file, array $settings, bool $checkSize, string $prefix = 'upload'): void
    {
        $name = (string) ($file['name'] ?? '');
        if ($name === '') {
            throw new \RuntimeException(_t('没有选择任何附件文件'));
        }

        if (($settings['uploadDoubleExt'] ?? '1') === '1' && preg_match('/\.(?:php|phtml|phar|jsp|asp|cgi)\.[a-z0-9]+$/i', $name)) {
            self::denyUpload($prefix . '.double-ext', '文件名包含双扩展名，已被拦截', ['name' => $name]);
        }

        if ($checkSize) {
            $size = (int) ($file['size'] ?? 0);
            $maxKb = (int) ($settings['uploadMaxKb'] ?? 0);
            if ($maxKb > 0 && $size > ($maxKb * 1024)) {
                self::denyUpload($prefix . '.size', '附件大小超过插件限制', [
                    'name' => $name,
                    'size' => $size,
                    'max_kb' => $maxKb,
                ]);
            }
        }

        $sample = self::uploadSample($file);
        if (!self::uploadMimeMatches($name, (string) ($file['tmp_name'] ?? ''), $sample)) {
            self::denyUpload($prefix . '.mime', '上传文件类型与内容特征不一致，已被拦截', ['name' => $name]);
        }

        if (($settings['uploadScan'] ?? '1') === '1' && self::uploadPayloadRisk($name, $sample)) {
            self::denyUpload($prefix . '.payload', '上传内容包含可疑特征，已被拦截', ['name' => $name]);
        }
    }

    private static function uploadMimeMatches(string $name, string $tmpPath, string $sample): bool
    {
        $ext = self::uploadExt($name);
        if ($ext === '') {
            return true;
        }

        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return str_starts_with($sample, "\xFF\xD8\xFF");
        }

        if ($ext === 'png') {
            return str_starts_with($sample, "\x89PNG\x0D\x0A\x1A\x0A");
        }

        if ($ext === 'gif') {
            return str_starts_with($sample, 'GIF87a') || str_starts_with($sample, 'GIF89a');
        }

        if ($ext === 'webp') {
            return str_starts_with($sample, 'RIFF') && str_contains(substr($sample, 0, 16), 'WEBP');
        }

        if ($ext === 'svg') {
            return preg_match('/<svg\b|<\?xml/i', $sample) === 1;
        }

        $mime = self::uploadMime($tmpPath, $sample);
        if ($mime === '') {
            return true;
        }

        $expected = [
            'txt' => ['text/plain'],
            'md' => ['text/plain', 'text/markdown'],
            'json' => ['application/json', 'text/plain'],
            'xml' => ['application/xml', 'text/xml'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'pdf' => ['application/pdf'],
        ];

        return !isset($expected[$ext]) || in_array($mime, $expected[$ext], true);
    }

    private static function uploadMime(string $tmpPath, string $sample): string
    {
        if ($tmpPath !== '' && is_file($tmpPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        if ($tmpPath !== '' && is_file($tmpPath) && function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpPath);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }

        if (str_starts_with($sample, '%PDF-')) {
            return 'application/pdf';
        }

        return '';
    }

    private static function coreUpload(array $file): array
    {
        $ext = self::uploadExt((string) $file['name']);
        if (!\Widget\Upload::checkFileType($ext)) {
            throw new \RuntimeException(_t('文件扩展名不被支持'));
        }

        $date = new Date();
        $fileName = sprintf('%u', crc32(uniqid('', true))) . '.' . $ext;
        $relativePath = (defined('__TYPECHO_UPLOAD_DIR__') ? (string) constant('__TYPECHO_UPLOAD_DIR__') : \Widget\Upload::UPLOAD_DIR)
            . '/' . $date->year . '/' . $date->month . '/' . $fileName;
        $target = Common::url(
            $relativePath,
            defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? (string) constant('__TYPECHO_UPLOAD_ROOT_DIR__') : __TYPECHO_ROOT_DIR__
        );
        self::storeUploadedFile($file, $target);

        $size = isset($file['size']) ? (int) $file['size'] : (int) filesize($target);

        return [
            'name' => (string) $file['name'],
            'path' => $relativePath,
            'size' => $size,
            'type' => $ext,
            'mime' => Common::mimeContentType($target),
        ];
    }

    private static function coreModify(array $content, array $file): array
    {
        $path = Common::url(
            (string) $content['attachment']->path,
            defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? (string) constant('__TYPECHO_UPLOAD_ROOT_DIR__') : __TYPECHO_ROOT_DIR__
        );
        self::storeUploadedFile($file, $path);

        $size = isset($file['size']) ? (int) $file['size'] : (int) filesize($path);
        return [
            'name' => $content['attachment']->name,
            'path' => $content['attachment']->path,
            'size' => $size,
            'type' => $content['attachment']->type,
            'mime' => Common::mimeContentType($path),
        ];
    }

    private static function storeUploadedFile(array $file, string $target): void
    {
        \Widget\Upload::replaceUploadedFile($file, $target);
    }

    private static function browserMinVersion(string $browser, array $settings): int
    {
        return match ($browser) {
            'chrome' => (int) ($settings['minChrome'] ?? 90),
            'firefox' => (int) ($settings['minFirefox'] ?? 90),
            'edge' => (int) ($settings['minEdge'] ?? 90),
            'safari' => (int) ($settings['minSafari'] ?? 13),
            default => 0,
        };
    }

    public static function unbanIp(string $ip): void
    {
        $ip = trim($ip);
        if ($ip === '') {
            return;
        }

        State::delete('ban:' . sha1($ip));
        Log::write('site', 'ban', 'allow', 'ip.unban', 0, '已手动解除 IP 封禁', ['ip' => $ip]);
    }

    private static function hasUntrustedProxyHeaders(Context $context, array $settings): bool
    {
        if (self::ipAllowed($context->ip, (string) ($settings['proxyTrusted'] ?? ''))) {
            return false;
        }

        foreach (['via', 'forwarded', 'x-forwarded-for', 'client-ip'] as $key) {
            if (!empty($context->headers[$key])) {
                return true;
            }
        }

        return false;
    }

    private static function headersComplete(Context $context): bool
    {
        foreach (['accept', 'accept-language', 'accept-encoding'] as $key) {
            if (empty($context->headers[$key])) {
                return false;
            }
        }

        return true;
    }

    private static function hasSmugglingSignals(Context $context): bool
    {
        $transfer = strtolower((string) ($context->headers['transfer-encoding'] ?? ''));
        $contentLength = (string) ($context->headers['content-length'] ?? '');

        if ($transfer !== '' && $contentLength !== '') {
            return true;
        }

        return str_contains($contentLength, ',');
    }

    private static function maybeCleanup(array $settings): void
    {
        $cacheKey = 'cleanup:' . date('YmdH');
        if (State::get($cacheKey, null) !== null) {
            return;
        }

        State::set($cacheKey, 1, 3700);
        Log::cleanup((int) ($settings['logKeepDays'] ?? 30));
        State::cleanup();
    }

    private static function safeReturn(string $uri): string
    {
        $uri = trim($uri);
        if ($uri === '' || !str_starts_with($uri, '/') || str_starts_with($uri, '//')) {
            return '/';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $uri) === 1) {
            return '/';
        }

        return $uri;
    }

    private static function safeRedirect(string $uri): string
    {
        $settings = Settings::normalize(['accessRedirect' => $uri]);
        $redirect = trim((string) ($settings['accessRedirect'] ?? ''));

        if ($redirect === '') {
            return '/';
        }

        if (preg_match('#^https?://#i', $redirect) === 1) {
            return $redirect;
        }

        return self::safeReturn($redirect);
    }

    private static function ban(string $ip, int $ttl, string $reason): void
    {
        if ($ip === '') {
            return;
        }

        State::set('ban:' . sha1($ip), ['reason' => $reason], max(60, $ttl));
        Log::write('site', 'ban', 'observe', 'ip.ban', 90, 'IP 已被临时封禁', [
            'ip' => $ip,
            'reason' => $reason,
            'ttl' => max(60, $ttl),
        ]);
    }

    private static function isBanned(string $ip): bool
    {
        return $ip !== '' && State::get('ban:' . sha1($ip), null) !== null;
    }

    private static function ipAllowed(string $ip, string $rules): bool
    {
        $ip = strtolower(trim($ip));
        if ($ip === '') {
            return false;
        }

        foreach (Text::lines($rules, 64, 200) as $line) {
            $line = strtolower(trim($line));
            if ($line === $ip) {
                return true;
            }

            if (str_contains($line, '/') && self::cidrMatch($ip, $line)) {
                return true;
            }
        }

        return false;
    }

    private static function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $bits = (int) $bits;
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton((string) $subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        $remainBits = $bits % 8;
        if ($remainBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainBits)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }

    private static function trapHit(string $path, string $rules): bool
    {
        foreach (Text::lines($rules, 255, 200) as $rule) {
            if ($rule === '') {
                continue;
            }

            if (str_contains($rule, '*')) {
                $pattern = '#^' . str_replace('\*', '.*', preg_quote(rtrim($rule, '/'), '#')) . '/?$#i';
                if (preg_match($pattern, rtrim($path, '/')) === 1) {
                    return true;
                }
                continue;
            }

            if (rtrim($path, '/') === rtrim($rule, '/')) {
                return true;
            }
        }

        return false;
    }

    private static function isScriptUa(string $ua, string $denyList): bool
    {
        foreach (Text::lines($denyList, 255, 200) as $rule) {
            if ($rule !== '' && stripos($ua, $rule) !== false) {
                return true;
            }
        }

        return preg_match('/curl|wget|python-requests|python-urllib|go-http-client|java\/|okhttp|aiohttp|httpclient|libwww-perl|powershell|node-fetch|axios|guzzlehttp|postman|insomnia|scrip[t]/i', $ua) === 1;
    }

    private static function uaAllowed(string $ua, string $allowList): bool
    {
        foreach (Text::lines($allowList, 255, 200) as $rule) {
            if ($rule !== '' && stripos($ua, $rule) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function commentNeedsChallenge(Context $context): bool
    {
        $body = strtolower(Text::cut($context->body, 4096));
        if ($body === '') {
            return true;
        }

        if (preg_match_all('#https?://#i', $body, $matches) > 0) {
            return true;
        }

        if (!$context->claimsBrowser) {
            return true;
        }

        return !self::headersComplete($context);
    }

    private static function loginPairKey(string $ip, string $name): string
    {
        return 'login:fail:' . sha1(strtolower(trim($ip) . '|' . trim($name)));
    }

    private static function loginNameKey(string $name): string
    {
        return 'login:fail:name:' . sha1(strtolower(trim($name)));
    }
}
