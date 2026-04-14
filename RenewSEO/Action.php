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
                $this->success('运行日志已清空');
                return;
            case 'clear_404':
                Log::purge404();
                $this->success('404 监控记录已清空');
                return;
            case 'cleanup':
                $settings = Settings::load();
                Log::cleanup((int) $settings['logKeepDays'], (int) $settings['notFoundKeepDays']);
                $this->success('日志清理已执行');
                return;
            case 'test_push':
                $this->testPush();
                return;
        }

        $this->error('未知操作');
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
        $data = [
            'enabled' => '0',
            'logAutoClean' => '0',
            'pushAsync' => '0',
            'robotsEnable' => '0',
            'robotsSitemap' => '0',
            'sitemapEnable' => '0',
            'sitemapTxt' => '0',
            'sitemapPage' => '0',
            'sitemapCategory' => '0',
            'sitemapTag' => '0',
            'sitemapAuthor' => '0',
            'ogEnable' => '0',
            'timeEnable' => '0',
            'canonicalEnable' => '0',
            'noindexSearch' => '0',
            'noindex404' => '0',
            'altEnable' => '0',
            'baiduEnable' => '0',
            'baiduQuick' => '0',
            'baiduPushOnEdit' => '0',
            'indexNowEnable' => '0',
            'indexNowOnEdit' => '0',
            'bingEnable' => '0',
            'bingOnEdit' => '0',
        ];
        $submitted = array_filter(
            $this->request->from('enabled', 'cacheTtl', 'panelSize', 'logKeepDays', 'notFoundKeepDays', 'logAutoClean', 'pushAsync', 'pushTimeout', 'robotsEnable', 'robotsDefault', 'robotsMode', 'robotsAllowed', 'robotsDenied', 'robotsBlocked', 'robotsExtra', 'robotsSitemap', 'robotsCustomSitemaps', 'sitemapEnable', 'sitemapTxt', 'sitemapSplit', 'sitemapDebounce', 'sitemapPage', 'sitemapCategory', 'sitemapTag', 'sitemapAuthor', 'sitemapPriorityHome', 'sitemapPriorityPost', 'sitemapPriorityPage', 'sitemapPriorityCategory', 'sitemapPriorityTag', 'sitemapPriorityAuthor', 'sitemapFreqHome', 'sitemapFreqPost', 'sitemapFreqPage', 'sitemapFreqTaxonomy', 'ogEnable', 'ogDefaultImage', 'timeEnable', 'canonicalEnable', 'canonicalStrip', 'noindexSearch', 'noindex404', 'altEnable', 'altTemplate', 'baiduEnable', 'baiduToken', 'baiduQuick', 'baiduDays', 'baiduPushOnEdit', 'indexNowEnable', 'indexNowKey', 'indexNowKeyPath', 'indexNowOnEdit', 'bingEnable', 'bingApiKey', 'bingOnEdit'),
            static fn($value): bool => $value !== null
        );
        $data = array_merge($data, $submitted);
        $next = Settings::normalize(array_merge($previous, $data));
        Settings::store($next);
        Files::sync('save', true);
        Files::cleanupTransition($previous, $next);
        $this->success('SEO 设置已保存并同步文件');
    }

    private function rebuild(): void
    {
        $result = Files::sync('manual', true);
        if (!empty($result['disabled'])) {
            $this->success('插件已暂停，已清理已有 SEO 文件');
            return;
        }
        if (!empty($result['ok'])) {
            $this->success('SEO 文件已重建');
            return;
        }
        $this->error('重建失败：' . (string) ($result['message'] ?? 'unknown'));
    }

    private function testPush(): void
    {
        $url = trim((string) $this->request->get('url'));
        if ($url === '') {
            $this->error('测试推送地址不能为空');
        }

        $result = Push::manualUrl($url);
        $ok = !empty($result['baidu']['ok']) || !empty($result['indexnow']['ok']) || !empty($result['bing']['ok']);
        if ($ok) {
            $this->success('手动推送已执行');
            return;
        }
        $this->error('手动推送执行失败，请查看日志');
    }

    private function guard(): void
    {
        User::alloc()->pass('administrator');
        Security::alloc()->protect();
    }

    private function success(string $message): void
    {
        Notice::alloc()->set($message, 'success');
        $this->response->redirect(Settings::panelUrl());
    }

    private function error(string $message): void
    {
        Notice::alloc()->set($message, 'error');
        $this->response->redirect(Settings::panelUrl());
    }
}
