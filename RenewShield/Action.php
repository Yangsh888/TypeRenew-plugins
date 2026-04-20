<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Widget\Notice;
use Widget\Security;
use Widget\User;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends \Typecho\Widget
{
    public function action(): void
    {
        $do = trim((string) $this->request->get('do'));
        if ($do === 'challenge') {
            $this->challenge();
            return;
        }

        $this->guard();
        match ($do) {
            'save' => $this->save(),
            'purge_logs' => $this->purgeLogs(),
            'cleanup' => $this->cleanup(),
            'unban' => $this->unban(),
            default => $this->error('未知操作'),
        };
    }

    private function challenge(): void
    {
        $token = trim((string) $this->request->get('token'));
        $confirm = trim((string) $this->request->get('confirm'));
        if ($confirm !== '1') {
            Log::write('site', 'challenge', 'observe', 'challenge.confirm', 10, '挑战页未完成点击确认', []);
            Notice::alloc()->set('请在验证页完成确认后再继续访问', 'notice');
            $this->response->redirect(Settings::siteUrl());
        }

        $result = Guard::verifyChallengeToken($token);
        if (!$result['ok']) {
            Notice::alloc()->set((string) ($result['message'] ?? '验证失败'), 'error');
            $this->response->redirect(Settings::siteUrl());
        }

        $this->response->redirect((string) ($result['redirect'] ?? Settings::siteUrl()));
    }

    private function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error('保存操作必须通过 POST 提交');
        }

        $data = [];
        $tab = $this->tab((string) ($_POST['tab'] ?? 'global'));
        $applyProfile = trim((string) ($_POST['apply_profile'] ?? '')) === '1';
        foreach (Settings::boolKeys() as $key) {
            $data[$key] = '0';
        }

        foreach (array_keys(Settings::defaults()) as $key) {
            if (array_key_exists($key, $_POST)) {
                $data[$key] = $_POST[$key];
            }
        }

        $current = Settings::loadFresh();
        $next = array_merge($current, $data);
        if ($applyProfile) {
            Settings::storeProfile($next);
        } else {
            Settings::store($next);
        }

        $saved = Settings::loadFresh();
        $issues = Access::issues($saved);
        if ($issues === []) {
            Notice::alloc()->set($applyProfile ? '已应用推荐预设并保存配置' : '配置已保存', 'success');
        } else {
            $prefix = $applyProfile ? '已应用推荐预设并保存配置' : '配置已保存';
            Notice::alloc()->set($prefix . '，但访问规则中仍有问题：' . implode('；', array_slice($issues, 0, 2)), 'notice');
        }

        $this->response->redirect(Settings::panelQueryUrl($tab === 'global' ? [] : ['tab' => $tab]));
    }

    private function purgeLogs(): void
    {
        Log::purge();
        Notice::alloc()->set('日志已清空', 'success');
        $this->response->redirect(Settings::panelQueryUrl(['tab' => 'ops']));
    }

    private function cleanup(): void
    {
        $settings = Settings::loadFresh();
        Log::cleanup((int) ($settings['logKeepDays'] ?? 30));
        State::cleanup();
        Notice::alloc()->set('已清理过期日志和状态', 'success');
        $this->response->redirect(Settings::panelQueryUrl(['tab' => 'ops']));
    }

    private function unban(): void
    {
        $ip = trim((string) $this->request->get('ip'));
        if ($ip === '') {
            Notice::alloc()->set('请输入要解除封禁的 IP', 'notice');
            $this->response->redirect(Settings::panelQueryUrl(['tab' => 'ops']));
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Notice::alloc()->set('IP 格式无效', 'error');
            $this->response->redirect(Settings::panelQueryUrl(['tab' => 'ops']));
        }

        Guard::unbanIp($ip);
        Notice::alloc()->set('已解除该 IP 的封禁状态', 'success');
        $this->response->redirect(Settings::panelQueryUrl(['tab' => 'ops']));
    }

    private function guard(): void
    {
        User::alloc()->pass('administrator');
        Security::alloc()->protect();
    }

    private function error(string $message): never
    {
        Notice::alloc()->set($message, 'error');
        $this->response->redirect(Settings::panelUrl());
        exit;
    }

    private function tab(string $tab): string
    {
        $tab = trim($tab);
        return in_array($tab, Settings::tabs(), true) ? $tab : 'global';
    }
}
