<?php
declare(strict_types=1);

use TypechoPlugin\RenewSEO\Settings;
use TypechoPlugin\RenewSEO\Text;
?>
<div class="renewseo-card">
    <div class="renewseo-card-header renewseo-card-actions">
        <h3 class="renewseo-card-title">运行日志</h3>
        <form method="post" action="<?php echo Text::e(Settings::actionUrl('clear_logs', true)); ?>">
            <button type="submit" class="btn btn-s btn-warn">清空日志</button>
        </form>
    </div>

    <div class="renewseo-table-wrapper">
        <table class="typecho-list-table renewseo-table">
            <thead>
                <tr>
                    <th>时间</th>
                    <th>通道</th>
                    <th>动作</th>
                    <th>级别</th>
                    <th>目标</th>
                    <th>消息</th>
                    <th>详情</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="renewseo-empty-cell">
                            <div class="tr-panel-empty">暂无运行日志</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $row): ?>
                        <tr>
                            <td><?php echo Text::time((int) $row['created_at']); ?></td>
                            <td><?php echo Text::e((string) $row['channel']); ?></td>
                            <td><?php echo Text::e((string) $row['action']); ?></td>
                            <td><?php echo Text::e((string) $row['level']); ?></td>
                            <td class="mono"><?php echo Text::e((string) $row['target']); ?></td>
                            <td><?php echo Text::e((string) $row['message']); ?></td>
                            <td class="mono">
                                <?php if ((string) ($row['payload'] ?? '') !== ''): ?>
                                    <details class="tr-panel-details">
                                        <summary>查看</summary>
                                        <pre><?php echo Text::e((string) $row['payload']); ?></pre>
                                    </details>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
