<?php
declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TypechoPlugin\RenewSEO\Files;
use TypechoPlugin\RenewSEO\Log;
use TypechoPlugin\RenewSEO\Settings;
use TypechoPlugin\RenewSEO\Text;

$user->pass('administrator');
$settings = Settings::loadFresh();
$logs = Log::recentLogs((int) $settings['panelSize']);
$notFound = Log::recent404((int) $settings['panelSize']);
$files = Files::status($settings);
$robotsPreview = Files::buildRobots($settings);
$freqOptions = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
?>
<link rel="stylesheet" href="<?php echo Text::e(Settings::assetUrl('assets/panel.css')); ?>">
<div class="tr-panel tr-panel-seo">
    <section class="tr-card">
        <div class="tr-card-b">
            <div class="tr-panel-head">
                <div class="tr-panel-heading">
                    <h2 class="tr-panel-title">SEO 中心</h2>
                </div>
            </div>
        </div>
    </section>

    <nav class="tr-panel-tabs" aria-label="SEO 模块导航">
        <button type="button" class="tr-panel-tab is-active" data-target="global">概览与全局</button>
        <button type="button" class="tr-panel-tab" data-target="spiders">爬虫与收录</button>
        <button type="button" class="tr-panel-tab" data-target="page">页面 SEO</button>
        <button type="button" class="tr-panel-tab" data-target="push">主动推送</button>
    </nav>

    <?php require __DIR__ . '/view/form.php'; ?>

    <div class="tr-panel-pane is-active" data-tab="global">
        <?php require __DIR__ . '/view/files.php'; ?>
        <?php require __DIR__ . '/view/logs.php'; ?>
        <?php require __DIR__ . '/view/notfound.php'; ?>
    </div>

    <div class="tr-panel-sticky">
        <button type="submit" form="renewseo-main-form" class="btn primary">保存全部配置</button>
    </div>
</div>
<script src="<?php echo Text::e(Settings::assetUrl('assets/panel.js')); ?>"></script>
