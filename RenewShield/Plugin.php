<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Typecho\Plugin as Hook;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Utils\Helper;
use Utils\NoPersonal;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 【TypeRenew 专用】轻量安全中心
 *
 * @package RenewShield
 * @author TypeRenew
 * @link https://www.typerenew.com/
 * @version 1.1.0
 * @since 1.4.1
 */
class Plugin implements PluginInterface
{
    use NoPersonal;

    public static function activate(): string
    {
        Log::createTables();
        Settings::ensureStored();
        self::registerHooks();
        Helper::removeRoute('renew_shield_action');
        Helper::addRoute('renew_shield_action', '/action/renew-shield', Action::class, 'action');
        Helper::removePanel(3, 'RenewShield/Panel.php');
        Helper::addPanel(3, 'RenewShield/Panel.php', '安全中心', '安全中心', 'administrator');
        return _t('RenewShield 已启用');
    }

    public static function deactivate(): string
    {
        Guard::clearPass();
        Helper::removeRoute('renew_shield_action');
        Helper::removePanel(3, 'RenewShield/Panel.php');
        Settings::clear();
        return _t('RenewShield 已停用');
    }

    public static function config(Form $form): void
    {
        $settings = Settings::load();
        $enabled = new Form\Element\Radio(
            'enabled',
            ['1' => _t('启用'), '0' => _t('停用')],
            $settings['enabled'] ?? '1',
            _t('插件状态'),
            _t('更多设置请前往“安全中心”。')
        );
        $form->addInput($enabled);

        // 兼容旧的插件配置页：完整配置仍存于插件设置项中，
        // 这里补齐隐藏字段，避免 Config 回填未声明字段时报错。
        foreach (Settings::defaults() as $key => $default) {
            if ($key === 'enabled') {
                continue;
            }

            $form->addInput(new Form\Element\Hidden($key, null, (string) ($settings[$key] ?? $default)));
        }
    }

    public static function configHandle(array $settings, bool $_isInit): void
    {
        $current = Settings::load();
        Settings::store(array_merge($current, $settings));
    }

    private static function registerHooks(): void
    {
        Hook::factory('index.php')->begin = [Guard::class, 'boot'];
        Hook::factory('Widget\\Archive')->beforeRender = [Guard::class, 'archive'];
        Hook::factory('Widget\\Archive')->header = [Guard::class, 'header'];
        Hook::factory('Widget\\Archive')->footer = [Guard::class, 'footer'];
        Hook::factory('Widget\\Feedback')->comment = [Guard::class, 'comment'];
        Hook::factory('Widget\\User')->loginSucceed = [Guard::class, 'loginSucceed'];
        Hook::factory('Widget\\User')->loginFail = [Guard::class, 'loginFail'];
        Hook::factory('Widget\\Upload')->uploadHandle = [Guard::class, 'uploadHandle'];
        Hook::factory('Widget\\Upload')->modifyHandle = [Guard::class, 'modifyHandle'];
    }
}
