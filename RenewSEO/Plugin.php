<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewSEO;

use Typecho\Plugin as Hook;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Fake;
use Utils\Helper;
use Utils\NoPersonal;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 【TypeRenew 专用】SEO 功能拓展
 *
 * @package RenewSEO
 * @author TypeRenew
 * @link https://www.typerenew.com/
 * @version 1.1.0
 * @since 1.4.1
 */
class Plugin implements PluginInterface
{
    use NoPersonal;

    private static array $stash = [];

    public static function activate(): string
    {
        self::registerHooks();
        Log::createTables();
        Settings::ensureStored();
        Helper::removeRoute('renew_seo_action');
        Helper::addRoute('renew_seo_action', '/action/renew-seo', Action::class, 'action');
        Helper::removePanel(3, 'RenewSEO/Panel.php');
        Helper::addPanel(3, 'RenewSEO/Panel.php', 'SEO 中心', 'SEO 中心', 'administrator');
        Files::sync('activate', true);
        return _t('RenewSEO 已启用');
    }

    public static function deactivate(): string
    {
        Helper::removeRoute('renew_seo_action');
        Helper::removePanel(3, 'RenewSEO/Panel.php');
        return _t('RenewSEO 已停用');
    }

    public static function config(Form $form): void
    {
        $settings = Settings::load();

        $enabled = new Form\Element\Radio(
            'enabled',
            ['1' => _t('启用'), '0' => _t('暂停输出与自动任务')],
            $settings['enabled'] ?? '1',
            _t('运行状态'),
            _t('完整配置请前往“SEO 中心”面板。')
        );
        $form->addInput($enabled);

        foreach ($settings as $key => $value) {
            if ($key === 'enabled') {
                continue;
            }

            $fake = new Fake((string) $key, $value);
            $fake->input->setAttribute('type', 'hidden');
            $fake->input->setAttribute('style', 'display:none');
            $form->addInput($fake);
        }
    }

    public static function configHandle(array $settings, bool $_isInit): void
    {
        $current = Settings::load();
        $merged = array_merge($current, $settings);
        Settings::store($merged);
    }

    public static function captureWrite(array $contents, $editor): array
    {
        if (method_exists($editor, 'have') && $editor->have()) {
            $cid = (int) ($editor->cid ?? 0);
            if ($cid > 0) {
                $snapshot = Files::contentItem($cid);
                if ($snapshot) {
                    self::$stash['write:' . $cid] = $snapshot;
                }
            }
        }

        return $contents;
    }

    public static function captureDelete(int $cid, $_editor): void
    {
        $snapshot = Files::contentItem($cid);
        if ($snapshot) {
            self::$stash['delete:' . $cid] = $snapshot;
        }
    }

    public static function finishPublish(array $_contents, $editor): void
    {
        self::afterPublish($editor);
    }

    public static function finishSave(array $_contents, $editor): void
    {
        $settings = Settings::load();
        if (($settings['baiduPushOnEdit'] ?? '0') === '1'
            || ($settings['indexNowOnEdit'] ?? '1') === '1'
            || ($settings['bingOnEdit'] ?? '1') === '1'
        ) {
            self::afterPublish($editor);
        }
    }

    public static function finishMark(string $status, int $cid, $_editor): void
    {
        $item = Files::contentItem($cid);
        if (!$item) {
            return;
        }

        $urls = [];
        $deleted = [];
        if ($status === 'publish') {
            $urls[] = $item;
        } else {
            $item['deleted'] = true;
            $deleted[] = $item;
        }

        Push::schedule($urls, $deleted, true, 'mark:' . $status);
    }

    public static function finishDelete(int $cid, $_editor): void
    {
        $item = self::$stash['delete:' . $cid] ?? null;
        unset(self::$stash['delete:' . $cid]);
        if (!$item) {
            return;
        }

        $item['deleted'] = true;
        Push::schedule([], [$item], true, 'delete');
    }

    private static function afterPublish($editor): void
    {
        $cid = (int) ($editor->cid ?? 0);
        if ($cid <= 0) {
            return;
        }

        $current = Files::contentItem($cid);
        if (!$current || ($current['status'] ?? '') !== 'publish') {
            return;
        }

        $urls = [$current];
        $deleted = [];
        $before = self::$stash['write:' . $cid] ?? null;
        unset(self::$stash['write:' . $cid]);

        if ($before && !empty($before['url']) && $before['url'] !== $current['url']) {
            $before['deleted'] = true;
            $deleted[] = $before;
        }

        Push::schedule($urls, $deleted, Files::shouldSyncNow(Settings::load()), 'publish');
    }

    private static function registerHooks(): void
    {
        foreach (['admin/write-post.php', 'admin/write-page.php'] as $screen) {
            Hook::factory($screen)->option = [Meta::class, 'fields'];
        }

        foreach ([
            'indexHandle',
            'singleHandle',
            'archiveHandle',
            'categoryHandle',
            'tagHandle',
            'authorHandle',
            'dateHandle',
            'searchHandle',
            'error404Handle',
        ] as $handle) {
            Hook::factory('Widget\\Archive')->{$handle} = [Meta::class, 'archive'];
        }
        Hook::factory('Widget\\Archive')->headerOptions = [Meta::class, 'headerOptions'];
        Hook::factory('Widget\\Archive')->header = [Meta::class, 'header'];

        Hook::factory('Widget\\Base\\Contents')->contentEx = [Meta::class, 'contentEx'];

        foreach (['Widget\\Contents\\Post\\Edit', 'Widget\\Contents\\Page\\Edit'] as $editor) {
            Hook::factory($editor)->write = [self::class, 'captureWrite'];
            Hook::factory($editor)->finishPublish = [self::class, 'finishPublish'];
            Hook::factory($editor)->finishSave = [self::class, 'finishSave'];
            Hook::factory($editor)->delete = [self::class, 'captureDelete'];
            Hook::factory($editor)->finishDelete = [self::class, 'finishDelete'];
            Hook::factory($editor)->finishMark = [self::class, 'finishMark'];
        }
    }
}
