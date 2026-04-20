<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$user->pass('administrator');
$settings = RenewGo_Plugin::getSettings();
$db = \Typecho\Db::get();
$prefix = $db->getPrefix();
$limit = (int) ($settings['panelSize'] ?? 40);
$limit = max(10, min(200, $limit));
$logs = [];
$summary = (object) ['total' => 0, 'jump_success' => 0, 'jump_block' => 0];

try {
    $summaryTotal = $db->fetchObject($db->select(['COUNT(*)' => 'num'])->from($prefix . 'renew_go_logs'));
    $summarySuccess = $db->fetchObject($db->select(['COUNT(*)' => 'num'])
        ->from($prefix . 'renew_go_logs')
        ->where('action = ? AND result = ?', 'jump', 'success'));
    $summaryBlock = $db->fetchObject($db->select(['COUNT(*)' => 'num'])
        ->from($prefix . 'renew_go_logs')
        ->where('result = ?', 'rate-limit'));
    $summary = (object) [
        'total' => (int) ($summaryTotal->num ?? 0),
        'jump_success' => (int) ($summarySuccess->num ?? 0),
        'jump_block' => (int) ($summaryBlock->num ?? 0)
    ];
    $logs = $db->fetchAll($db->select()->from($prefix . 'renew_go_logs')
        ->order('id', \Typecho\Db::SORT_DESC)->limit($limit));
} catch (Throwable $e) {
    $logs = [];
}

$urlTest = RenewGo_Plugin::apiUrl('test');
$urlPurge = RenewGo_Plugin::apiUrl('purge');
$urlExport = RenewGo_Plugin::apiUrl('export');
$urlImport = RenewGo_Plugin::apiUrl('import');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(RenewGo_Plugin::assetUrl('assets/panel.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="tr-panel renewgo-panel">
    <section class="tr-card">
        <div class="tr-card-b">
            <div class="tr-panel-head">
                <div class="tr-panel-heading">
                    <h2 class="tr-panel-title"><?php _e('RenewGo 外链安全中心'); ?></h2>
                    <p class="tr-panel-desc"><?php _e('统一管理外链改写、规则校验、白名单维护与跳转日志，保持和 SEO 中心一致的后台体验。'); ?></p>
                </div>
                <div class="tr-panel-pills">
                    <span class="tr-pill"><?php echo htmlspecialchars((string) $settings['mode'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="tr-pill<?php echo ((string) $settings['enabled'] === '1') ? ' tr-pill-accent' : ''; ?>"><?php echo ((string) $settings['enabled'] === '1') ? _t('已启用') : _t('已停用'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <div class="tr-panel-kpis">
        <article class="tr-panel-kpi">
            <strong><?php echo (int) $summary->total; ?></strong>
            <span><?php _e('日志总数'); ?></span>
        </article>
        <article class="tr-panel-kpi">
            <strong><?php echo (int) $summary->jump_success; ?></strong>
            <span><?php _e('成功跳转'); ?></span>
        </article>
        <article class="tr-panel-kpi">
            <strong><?php echo (int) $summary->jump_block; ?></strong>
            <span><?php _e('频率拦截'); ?></span>
        </article>
        <article class="tr-panel-kpi">
            <strong><?php echo $limit; ?></strong>
            <span><?php _e('当前面板条数'); ?></span>
        </article>
    </div>

    <section class="tr-card">
        <div class="tr-card-h">
            <div class="tr-panel-section-head">
                <h3><?php _e('规则测试'); ?></h3>
            </div>
        </div>
        <div class="tr-card-b">
            <div class="renewgo-inline">
                <input id="renewgoTestUrl" class="tr-panel-input" type="text" placeholder="https://example.com/page">
                <button id="renewgoTestBtn" type="button" class="btn primary"><?php _e('测试规则'); ?></button>
            </div>
            <p class="tr-panel-note"><?php _e('用于验证某个 URL 是否命中白名单、是否被改写以及改写后的跳转地址。'); ?></p>
            <div id="renewgoTestResult" class="renewgo-result"></div>
        </div>
    </section>

    <section class="tr-card">
        <div class="tr-card-h">
            <div class="tr-panel-section-head">
                <h3><?php _e('白名单导入导出'); ?></h3>
                <div class="tr-panel-actions">
                    <button id="renewgoExportBtn" type="button" class="btn btn-s"><?php _e('导出'); ?></button>
                    <button id="renewgoImportBtn" type="button" class="btn btn-s primary"><?php _e('导入'); ?></button>
                </div>
            </div>
        </div>
        <div class="tr-card-b">
            <p class="tr-panel-note"><?php _e('每行一条规则，可直接编辑后导入；适合快速维护白名单和做跨站迁移。'); ?></p>
            <textarea id="renewgoRules" class="tr-panel-input renewgo-rules" spellcheck="false"><?php echo htmlspecialchars((string) ($settings['whitelist'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
    </section>

    <section class="tr-card">
        <div class="tr-card-h">
            <div class="tr-panel-section-head">
                <h3><?php _e('最近日志'); ?></h3>
                <div class="tr-panel-actions">
                    <button id="renewgoPurgeBtn" type="button" class="btn btn-s btn-warn"><?php _e('清空日志'); ?></button>
                </div>
            </div>
        </div>
        <div class="tr-card-b">
            <div class="typecho-table-wrap">
                <table class="typecho-list-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php _e('IP'); ?></th>
                        <th><?php _e('动作'); ?></th>
                        <th><?php _e('结果'); ?></th>
                        <th><?php _e('目标'); ?></th>
                        <th><?php _e('时间'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="renewgo-empty-cell">
                                <div class="tr-panel-empty"><?php _e('暂无日志记录'); ?></div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ((array) $logs as $row): ?>
                            <tr>
                                <td><?php echo (int) $row['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $row['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['result'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="mono"><?php echo htmlspecialchars((string) ($row['target'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo !empty($row['created_at']) ? date('Y-m-d H:i:s', (int) $row['created_at']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
window.RenewGoPanel = <?php echo json_encode([
    'test' => $urlTest,
    'purge' => $urlPurge,
    'export' => $urlExport,
    'import' => $urlImport
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo htmlspecialchars(RenewGo_Plugin::assetUrl('assets/panel.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
