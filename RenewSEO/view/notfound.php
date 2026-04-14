<?php
declare(strict_types=1);

use TypechoPlugin\RenewSEO\Settings;
use TypechoPlugin\RenewSEO\Text;
?>
<div class="renewseo-card">
    <div class="renewseo-card-header renewseo-card-actions">
        <h3 class="renewseo-card-title">404 监控记录</h3>
        <form method="post" action="<?php echo Text::e(Settings::actionUrl('clear_404', true)); ?>">
            <button type="submit" class="btn btn-s btn-warn">清空记录</button>
        </form>
    </div>

    <div class="renewseo-table-wrapper">
        <table class="typecho-list-table renewseo-table">
            <thead>
                <tr>
                    <th>最后命中</th>
                    <th>次数</th>
                    <th>路径</th>
                    <th>完整 URL</th>
                    <th>来源</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notFound)): ?>
                    <tr>
                        <td colspan="6" class="renewseo-empty-cell">
                            <div class="tr-panel-empty">暂无 404 记录</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($notFound as $row): ?>
                        <tr>
                            <td><?php echo Text::time((int) $row['last_seen']); ?></td>
                            <td><?php echo (int) $row['hits']; ?></td>
                            <td class="mono"><?php echo Text::e((string) $row['path']); ?></td>
                            <td class="mono"><?php echo Text::e((string) $row['full_url']); ?></td>
                            <td class="mono"><?php echo Text::e((string) $row['referer']); ?></td>
                            <td><?php echo Text::e((string) $row['ip']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
