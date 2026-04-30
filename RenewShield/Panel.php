<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TypechoPlugin\RenewShield\Health;
use TypechoPlugin\RenewShield\Log;
use TypechoPlugin\RenewShield\Settings;
use TypechoPlugin\RenewShield\Text;

$user->pass('administrator');
$settings = Settings::loadFresh();
$filters = [
    'scope' => trim((string) $request->get('scope')),
    'decision' => trim((string) $request->get('decision')),
    'action' => trim((string) $request->get('action')),
    'keyword' => trim((string) $request->get('keyword')),
];
$currentTab = trim((string) $request->get('tab', 'global'));
$tabs = Settings::tabs();
if (!in_array($currentTab, $tabs, true)) {
    $currentTab = 'global';
}
$page = max(1, (int) $request->get('page', 1));
$pageSize = (int) ($settings['panelSize'] ?? 10);

$insights = Log::insights();
$health = Health::inspect($settings);
$logs = Log::search($filters, $page, $pageSize);

$saveUrl = Settings::actionUrl('save', true);
$purgeUrl = Settings::actionUrl('purge_logs', true);
$cleanupUrl = Settings::actionUrl('cleanup', true);
$unbanUrl = Settings::actionUrl('unban', true);
$tabLabels = [
    'global' => '总览',
    'request' => '请求防护',
    'challenge' => '挑战与限频',
    'access' => '访问控制',
    'ops' => '健康与日志',
];
?>
<link rel="stylesheet" href="<?php echo Text::e(Settings::assetUrl('assets/panel.css')); ?>">
<div class="tr-panel tr-panel-shield">
    <section class="tr-card">
        <div class="tr-card-b">
            <div class="tr-panel-head">
                <div class="tr-panel-heading">
                    <h2 class="tr-panel-title">安全中心</h2>
                    <p class="tr-panel-desc">用于配置请求防护、挑战限频、访问控制与日志查看。</p>
                </div>
                <div class="tr-panel-pills">
                    <span class="tr-pill<?php echo $settings['enabled'] === '1' ? ' tr-pill-accent' : ''; ?>">插件<?php echo $settings['enabled'] === '1' ? '已启用' : '已停用'; ?></span>
                    <span class="tr-pill">预设：<?php echo Text::e(Settings::profiles()[$settings['profile']] ?? '平衡模式'); ?></span>
                    <span class="tr-pill">WAF：<?php echo Text::e(Settings::wafModes()[$settings['wafMode']] ?? '平衡模式'); ?></span>
                    <span class="tr-pill">风险：<?php echo Text::e(Settings::riskModes()[$settings['riskMode']] ?? '基础验证'); ?></span>
                </div>
            </div>
        </div>
    </section>
    <nav class="tr-panel-tabs" aria-label="安全中心导航">
        <?php foreach ($tabLabels as $tab => $label): ?>
            <button type="button" class="tr-panel-tab<?php echo $currentTab === $tab ? ' is-active' : ''; ?>" data-target="<?php echo Text::e($tab); ?>"><?php echo Text::e($label); ?></button>
        <?php endforeach; ?>
    </nav>

    <?php require __DIR__ . '/view/form.php'; ?>

    <div class="tr-panel-pane<?php echo $currentTab === 'ops' ? ' is-active' : ''; ?>" data-tab="ops">
        <?php require __DIR__ . '/view/health.php'; ?>
        <?php require __DIR__ . '/view/logs.php'; ?>
    </div>
</div>
<script src="<?php $options->adminStaticUrl('js', 'tr-tabs.js'); ?>"></script>
<script src="<?php echo Text::e(Settings::assetUrl('assets/panel.js')); ?>"></script>
