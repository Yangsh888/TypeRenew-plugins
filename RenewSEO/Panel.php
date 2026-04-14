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
$settings = Settings::load();
$summary = Log::summary();
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
                    <p class="tr-panel-desc">统一管理 Robots、Sitemap、Canonical、OG、时间因子、主动推送与日志监控。</p>
                </div>
                <div class="tr-panel-pills">
                    <span class="tr-pill<?php echo $settings['enabled'] === '1' ? ' tr-pill-accent' : ''; ?>">插件<?php echo $settings['enabled'] === '1' ? '已启用' : '已暂停'; ?></span>
                    <span class="tr-pill<?php echo $settings['sitemapEnable'] === '1' ? ' tr-pill-accent' : ''; ?>">Sitemap</span>
                    <span class="tr-pill<?php echo $settings['robotsEnable'] === '1' ? ' tr-pill-accent' : ''; ?>">Robots</span>
                    <span class="tr-pill<?php echo ($settings['baiduEnable'] === '1' || $settings['indexNowEnable'] === '1' || $settings['bingEnable'] === '1') ? ' tr-pill-accent' : ''; ?>">主动推送</span>
                </div>
            </div>
        </div>
    </section>

    <div class="tr-panel-kpis">
        <article class="tr-panel-kpi"><strong><?php echo (int) $summary['logs']; ?></strong><span>运行日志</span></article>
        <article class="tr-panel-kpi"><strong><?php echo (int) $summary['errors']; ?></strong><span>错误日志</span></article>
        <article class="tr-panel-kpi"><strong><?php echo (int) $summary['notFound']; ?></strong><span>404 记录</span></article>
        <article class="tr-panel-kpi"><strong><?php echo (int) $summary['top404']; ?></strong><span>最高命中</span></article>
    </div>

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
