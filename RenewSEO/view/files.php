<?php
declare(strict_types=1);

use TypechoPlugin\RenewSEO\Settings;
use TypechoPlugin\RenewSEO\Text;
?>
<div class="renewseo-card">
    <div class="renewseo-card-header">
        <h3 class="renewseo-card-title">文件与工具</h3>
    </div>
    <div class="renewseo-file-grid">
        <?php foreach ($files as $file): ?>
            <div class="tr-panel-file-card">
                <div class="tr-panel-file-meta">
                    <span class="tr-panel-file-name"><?php echo Text::e($file['name']); ?></span>
                    <?php if (!empty($file['exists'])): ?>
                        <span class="tr-panel-badge is-success">已生成</span>
                    <?php else: ?>
                        <span class="tr-panel-badge">未生成</span>
                    <?php endif; ?>
                </div>
                <div class="tr-panel-file-info">
                    <span><?php echo Text::time((int) $file['mtime']); ?></span>
                    <span><?php echo number_format((int) $file['size']); ?> B</span>
                    <?php if (!empty($file['url'])): ?>
                        <a href="<?php echo Text::e($file['url']); ?>" class="tr-panel-file-link" target="_blank" rel="noopener noreferrer" title="查看文件">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="renewseo-tool-bar">
        <form method="post" action="<?php echo Text::e(Settings::actionUrl('rebuild', true)); ?>">
            <button type="submit" class="btn">重建文件</button>
        </form>
        <form method="post" action="<?php echo Text::e(Settings::actionUrl('cleanup', true)); ?>">
            <button type="submit" class="btn">执行自动清理</button>
        </form>
        <form method="post" action="<?php echo Text::e(Settings::actionUrl('test_push', true)); ?>">
            <input type="url" name="url" class="renewseo-input" placeholder="<?php echo Text::e(Settings::siteUrl()); ?>">
            <button type="submit" class="btn">手动推送</button>
        </form>
    </div>
</div>
