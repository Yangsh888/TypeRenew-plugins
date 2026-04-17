<?php
declare(strict_types=1);

use TypechoPlugin\RenewShield\Text;

$rows = (array) ($logs['rows'] ?? []);
$total = (int) ($logs['total'] ?? 0);
$currentPage = max(1, (int) ($logs['page'] ?? 1));
$pageSize = max(1, (int) ($logs['size'] ?? 10));
$totalPages = max(1, (int) ceil($total / $pageSize));
$scopeOptions = \TypechoPlugin\RenewShield\Log::scopeOptions();
$decisionOptions = \TypechoPlugin\RenewShield\Log::decisionOptions();
$actionOptions = \TypechoPlugin\RenewShield\Log::actionOptions();
$buildUrl = static fn(array $overrides = []): string => \TypechoPlugin\RenewShield\Settings::panelQueryUrl(
    array_merge($filters, ['tab' => 'ops'], $overrides)
);
?>
<div class="shield-card">
    <div class="shield-card-header">
        <h3 class="shield-card-title">最近 24 小时概览</h3>
        <p class="shield-card-desc">优先查看高频规则、高频来源和最近封禁来源，判断当前站点的主要风险类型。</p>
    </div>
    <div class="shield-card-pad">
        <div class="shield-overview">
            <article class="shield-overview-card">
                <strong><?php echo (int) ($insights['recent'] ?? 0); ?></strong>
                <span>最近日志</span>
            </article>
            <article class="shield-overview-card">
                <strong><?php echo (int) ($insights['blocked'] ?? 0); ?></strong>
                <span>直接拦截</span>
            </article>
            <article class="shield-overview-card">
                <strong><?php echo (int) ($insights['challenge'] ?? 0); ?></strong>
                <span>进入验证</span>
            </article>
            <article class="shield-overview-card">
                <strong><?php echo (int) ($insights['observe'] ?? 0); ?></strong>
                <span>仅观察</span>
            </article>
            <article class="shield-overview-card">
                <strong><?php echo (int) ($insights['anomaly'] ?? 0); ?></strong>
                <span>登录环境变化</span>
            </article>
        </div>
        <div class="shield-insight-grid shield-insight-grid-4">
            <?php foreach ([
                'rules' => '高频规则',
                'ips' => '高频 IP',
                'paths' => '高频路径',
                'bans' => '最近封禁 IP',
            ] as $key => $title): ?>
                <section class="shield-insight-card">
                    <h4 class="shield-insight-title"><?php echo Text::e($title); ?></h4>
                    <?php $items = (array) ($insights[$key] ?? []); ?>
                    <?php if ($items === []): ?>
                        <p class="shield-insight-empty">暂无数据</p>
                    <?php else: ?>
                        <ul class="shield-insight-list">
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <span><?php echo Text::e((string) ($item['name'] ?? '')); ?></span>
                                    <strong><?php echo (int) ($item['count'] ?? 0); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="shield-card">
    <div class="shield-card-header">
        <h3 class="shield-card-title">运行日志</h3>
        <p class="shield-card-desc">按范围、结果和关键字筛选日志，并可手动解除某个已被临时封禁的 IP。</p>
    </div>

    <div class="shield-card-pad">
        <div class="shield-toolbar">
            <form method="get" action="<?php echo Text::e(\TypechoPlugin\RenewShield\Settings::panelUrl()); ?>" class="shield-toolbar-form">
                <input type="hidden" name="tab" value="ops">
                <div class="shield-toolbar-main">
                    <select name="scope" class="shield-input">
                        <?php foreach ($scopeOptions as $value => $label): ?>
                            <option value="<?php echo Text::e($value); ?>"<?php echo $filters['scope'] === $value ? ' selected' : ''; ?>><?php echo Text::e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="decision" class="shield-input">
                        <?php foreach ($decisionOptions as $value => $label): ?>
                            <option value="<?php echo Text::e($value); ?>"<?php echo $filters['decision'] === $value ? ' selected' : ''; ?>><?php echo Text::e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="action" class="shield-input">
                        <?php foreach ($actionOptions as $value => $label): ?>
                            <option value="<?php echo Text::e($value); ?>"<?php echo $filters['action'] === $value ? ' selected' : ''; ?>><?php echo Text::e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input
                        type="text"
                        name="keyword"
                        value="<?php echo Text::e($filters['keyword']); ?>"
                        class="shield-input"
                        placeholder="路径、IP、UA、规则名或说明"
                    >
                </div>
                <div class="shield-toolbar-side">
                    <button type="submit" class="btn btn-s primary">筛选</button>
                    <a class="btn btn-s" href="<?php echo Text::e($buildUrl([
                        'scope' => '',
                        'decision' => '',
                        'action' => '',
                        'keyword' => '',
                        'page' => '1',
                    ])); ?>">重置</a>
                </div>
            </form>
            <div class="shield-toolbar-ops">
                <form method="post" action="<?php echo Text::e($unbanUrl); ?>" class="shield-unban-form">
                    <input type="hidden" name="tab" value="ops">
                    <input type="text" name="ip" class="shield-input w-ip" placeholder="输入 IP 解除封禁">
                    <button type="submit" class="btn btn-s">解除封禁</button>
                </form>
                <form method="post" action="<?php echo Text::e($cleanupUrl); ?>">
                    <input type="hidden" name="tab" value="ops">
                    <button type="submit" class="btn btn-s">清理过期数据</button>
                </form>
                <form method="post" action="<?php echo Text::e($purgeUrl); ?>">
                    <input type="hidden" name="tab" value="ops">
                    <button type="submit" class="btn btn-s btn-warn">清空日志</button>
                </form>
            </div>
        </div>

        <div class="shield-table-wrap">
            <table class="typecho-list-table shield-table">
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>范围</th>
                        <th>处理结果</th>
                        <th>规则</th>
                        <th>请求</th>
                        <th>说明</th>
                        <th>详情</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="7" class="shield-empty-cell">
                                <div class="tr-panel-empty">暂无匹配日志</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $payload = trim((string) ($row['payload'] ?? ''));
                            $scope = (string) ($row['scope'] ?? '');
                            $decision = (string) ($row['decision'] ?? '');
                            $action = (string) ($row['action'] ?? '');
                            $ruleView = \TypechoPlugin\RenewShield\Log::ruleView((string) ($row['rule_key'] ?? ''));
                            $payloadSummary = \TypechoPlugin\RenewShield\Log::payloadSummary($payload);
                            ?>
                            <tr>
                                <td class="shield-log-time"><?php echo Text::e(Text::time((int) ($row['created_at'] ?? 0))); ?></td>
                                <td class="shield-log-scope"><?php echo Text::e($scopeOptions[$scope] ?? ($scope !== '' ? $scope : '-')); ?></td>
                                <td class="shield-log-result">
                                    <span class="shield-badge shield-badge-<?php echo Text::e($decision !== '' ? $decision : 'observe'); ?>"><?php echo Text::e($decisionOptions[$decision] ?? ($decision !== '' ? $decision : '-')); ?></span>
                                    <?php if ($action !== ''): ?>
                                        <span class="shield-log-action"><?php echo Text::e($actionOptions[$action] ?? $action); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="shield-log-rule">
                                    <strong><?php echo Text::e((string) ($ruleView['label'] ?? '-')); ?></strong>
                                    <?php if (!empty($ruleView['group'])): ?>
                                        <div class="shield-log-sub"><?php echo Text::e((string) $ruleView['group']); ?></div>
                                    <?php endif; ?>
                                    <?php if (($ruleView['key'] ?? '') !== ($ruleView['label'] ?? '')): ?>
                                        <code><?php echo Text::e((string) ($ruleView['key'] ?? '')); ?></code>
                                    <?php endif; ?>
                                </td>
                                <td class="shield-log-target">
                                    <strong><?php echo Text::e((string) ($row['method'] ?? 'GET')); ?></strong>
                                    <span class="mono"><?php echo Text::e((string) ($row['path'] ?? '/')); ?></span>
                                    <small><?php echo Text::e((string) ($row['ip'] ?? '-')); ?></small>
                                </td>
                                <td class="shield-log-message">
                                    <div><?php echo Text::e((string) ($row['message'] ?? '-')); ?></div>
                                    <?php if (!empty($ruleView['hint'])): ?>
                                        <div class="shield-log-sub"><?php echo Text::e((string) $ruleView['hint']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="shield-log-detail">
                                    <?php if ($payload !== '' || $payloadSummary !== []): ?>
                                        <details class="tr-panel-details">
                                            <summary>查看</summary>
                                            <?php if ($payloadSummary !== []): ?>
                                                <ul class="shield-detail-list">
                                                    <?php foreach ($payloadSummary as $item): ?>
                                                        <li><?php echo Text::e((string) $item); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <pre><?php echo Text::e($payload); ?></pre>
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

        <?php if ($total > 0): ?>
            <div class="shield-page-nav">
                <span class="shield-page-status">共 <?php echo $total; ?> 条<?php if ($totalPages > 1): ?>，第 <?php echo $currentPage; ?> / <?php echo $totalPages; ?> 页<?php endif; ?></span>
                <div class="shield-page-actions">
                    <?php if ($currentPage > 1): ?>
                        <a class="tr-pill tr-pill-btn" href="<?php echo Text::e($buildUrl(['page' => (string) ($currentPage - 1)])); ?>">上一页</a>
                    <?php endif; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a class="tr-pill tr-pill-btn" href="<?php echo Text::e($buildUrl(['page' => (string) ($currentPage + 1)])); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
