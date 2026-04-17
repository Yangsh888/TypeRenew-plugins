<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

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

        if ($do === 'async') {
            $this->async();
            return;
        }

        $this->guard();

        switch ($do) {
            case 'save':
                $this->save();
                return;
            case 'rebuild':
                $this->rebuild();
                return;
            case 'clear_logs':
                Log::purgeLogs();
                $this->notice('运行日志已清空');
                return;
            case 'clear_404':
                Log::purge404();
                $this->notice('404 监控记录已清空');
                return;
            case 'cleanup':
                $settings = Settings::load();
                Log::cleanup((int) $settings['logKeepDays'], (int) $settings['notFoundKeepDays']);
                $this->notice('日志清理已执行');
                return;
            case 'test_push':
                $this->testPush();
                return;
        }

        $this->notice('未知操作', 'error');
    }

    private function async(): void
    {
        $data = $this->request->getJsonBody();
        $token = (string) ($data['token'] ?? '');
        $ts = (int) ($data['ts'] ?? 0);

        if (!Push::verifyAsyncToken($ts, $token)) {
            $this->response->setStatus(403);
            $this->response->throwJson(['ok' => false, 'message' => 'invalid token']);
        }

        $task = is_array($data['task'] ?? null) ? $data['task'] : [];
        $result = Push::process($task);
        $this->response->throwJson(['ok' => true, 'result' => $result]);
    }

    private function save(): void
    {
        $previous = Settings::loadFresh();
        $data = [];
        foreach (Settings::boolKeys() as $key) {
            $data[$key] = '0';
        }

        foreach (array_keys(Settings::defaults()) as $key) {
            if (array_key_exists($key, $_POST)) {
                $data[$key] = $_POST[$key];
                continue;
            }

            if (array_key_exists($key, $_GET)) {
                $data[$key] = $_GET[$key];
            }
        }

        $next = Settings::normalize(array_merge($previous, $data));
        Settings::store($next);
        Files::sync('save', true);
        Files::cleanupTransition($previous, $next);
        $this->notice('SEO 设置已保存并同步文件');
    }

    private function rebuild(): void
    {
        $result = Files::sync('manual', true);
        if (!empty($result['disabled'])) {
            $this->notice('插件已暂停，已清理已有 SEO 文件');
            return;
        }
        if (!empty($result['ok'])) {
            $this->notice('SEO 文件已重建');
            return;
        }
        $this->notice('重建失败：' . (string) ($result['message'] ?? 'unknown'), 'error');
    }

    private function testPush(): void
    {
        $url = trim((string) $this->request->get('url'));
        if ($url === '') {
            $this->notice('测试推送地址不能为空', 'error');
        }

        $result = Push::manualUrl($url);
        $ok = !empty($result['baidu']['ok']) || !empty($result['indexnow']['ok']) || !empty($result['bing']['ok']);
        if ($ok) {
            $this->notice('手动推送已执行');
            return;
        }
        $this->notice('手动推送执行失败，请查看日志', 'error');
    }

    private function guard(): void
    {
        User::alloc()->pass('administrator');
        Security::alloc()->protect();
    }

    private function notice(string $message, string $type = 'success'): void
    {
        Notice::alloc()->set($message, $type);
        $this->response->redirect(Settings::panelUrl());
    }
}
