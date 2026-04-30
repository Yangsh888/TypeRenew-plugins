<?php
declare(strict_types=1);

use TypechoPlugin\RenewShield\Text;
?>
<div class="shield-card">
    <div class="shield-card-header">
        <h3 class="shield-card-title">健康检查</h3>
        <p class="shield-card-desc">只显示当前值得优先处理的项目，并明确插件层与服务器层的边界。</p>
    </div>

    <div class="shield-card-pad">
        <div class="shield-health-grid">
        <?php if ($health === []): ?>
            <article class="shield-health shield-health-ok">
                <h4 class="shield-health-title">暂无待处理项</h4>
                <p class="shield-health-text">当前没有生成可展示的检查结果，可稍后刷新页面再次确认。</p>
            </article>
        <?php else: ?>
            <?php foreach ($health as $item): ?>
                <article class="shield-health shield-health-<?php echo Text::e((string) $item['level']); ?>">
                    <h4 class="shield-health-title"><?php echo Text::e((string) $item['title']); ?></h4>
                    <p class="shield-health-text"><?php echo Text::e((string) $item['message']); ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>
