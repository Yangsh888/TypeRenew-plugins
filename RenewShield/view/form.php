<?php
declare(strict_types=1);

use TypechoPlugin\RenewShield\Text;

$profiles = \TypechoPlugin\RenewShield\Settings::profiles();
$riskModes = \TypechoPlugin\RenewShield\Settings::riskModes();
$wafModes = \TypechoPlugin\RenewShield\Settings::wafModes();
$accessSummary = \TypechoPlugin\RenewShield\Access::summary($settings);
?>
<form id="renewshield-main-form" method="post" action="<?php echo Text::e($saveUrl); ?>">
    <input type="hidden" name="tab" value="<?php echo Text::e($currentTab); ?>">
    <input type="hidden" name="apply_profile" value="0" id="renewshield-apply-profile">

    <div class="tr-panel-pane<?php echo $currentTab === 'global' ? ' is-active' : ''; ?>" data-tab="global">
        <div class="shield-card">
            <div class="shield-card-header">
                <h3 class="shield-card-title">运行概况</h3>
                <p class="shield-card-desc">用于设置插件启用状态、预设方案和整体处理策略。点击按钮后，会按当前选中的预设保存相关策略值。</p>
            </div>
            <div class="shield-list">
                <div class="shield-list-item">
                    <div class="shield-list-item-meta">
                        <h4 class="shield-list-item-title">全局开关</h4>
                        <p class="shield-list-item-desc">关闭后插件停止请求防护，但不主动删除日志和状态数据。</p>
                    </div>
                    <div class="shield-list-item-control">
                        <label class="shield-switch">
                            <input type="checkbox" name="enabled" value="1"<?php echo $settings['enabled'] === '1' ? ' checked' : ''; ?>>
                            <span class="shield-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="shield-block-item">
                    <div class="shield-matrix">
                        <label class="shield-field">
                            <span>预设方案</span>
                            <select name="profile" class="shield-input">
                                <?php foreach ($profiles as $value => $label): ?>
                                    <option value="<?php echo Text::e($value); ?>"<?php echo $settings['profile'] === $value ? ' selected' : ''; ?>><?php echo Text::e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="shield-field">
                            <span>风险处理策略</span>
                            <select name="riskMode" class="shield-input">
                                <?php foreach ($riskModes as $value => $label): ?>
                                    <option value="<?php echo Text::e($value); ?>"<?php echo $settings['riskMode'] === $value ? ' selected' : ''; ?>><?php echo Text::e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="shield-field">
                            <span>WAF 处理策略</span>
                            <select name="wafMode" class="shield-input">
                                <?php foreach ($wafModes as $value => $label): ?>
                                    <option value="<?php echo Text::e($value); ?>"<?php echo $settings['wafMode'] === $value ? ' selected' : ''; ?>><?php echo Text::e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="shield-note-grid">
                        <article class="shield-note">
                            <strong>保守模式</strong>
                            <p>优先保证正常访问，并采用较温和的处理方式。</p>
                        </article>
                        <article class="shield-note">
                            <strong>平衡模式</strong>
                            <p>在访问体验与基础防护之间保持均衡。</p>
                        </article>
                        <article class="shield-note">
                            <strong>严格模式</strong>
                            <p>提高风险处理强度，适用于需要更严格限制的场景。</p>
                        </article>
                    </div>
                    <div class="shield-profile-bar">
                        <div class="shield-profile-copy">
                            <strong>预设方案</strong>
                            <p>点击按钮后，会按当前选中的预设保存相关策略值；如需保留当前手动修改，请使用页面底部的“保存当前配置”。</p>
                        </div>
                        <button type="button" class="btn" data-shield-apply-profile="1">使用当前预设并保存</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="shield-card">
            <div class="shield-card-header">
                <h3 class="shield-card-title">面板与存储</h3>
                <p class="shield-card-desc">这些设置只影响插件自身配置缓存、面板分页和日志保留策略，不会改变防护判断。</p>
            </div>
            <div class="shield-list">
                <div class="shield-block-item">
                    <div class="shield-matrix">
                        <label class="shield-field">
                            <span>配置缓存秒数</span>
                            <input type="number" name="cacheTtl" min="30" max="3600" value="<?php echo (int) $settings['cacheTtl']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>日志每页数量</span>
                            <input type="number" name="panelSize" min="10" max="200" value="<?php echo (int) $settings['panelSize']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>日志保留天数</span>
                            <input type="number" name="logKeepDays" min="1" max="365" value="<?php echo (int) $settings['logKeepDays']; ?>" class="shield-input">
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tr-panel-pane<?php echo $currentTab === 'request' ? ' is-active' : ''; ?>" data-tab="request">
        <div class="shield-card">
            <div class="shield-card-header">
                <h3 class="shield-card-title">基础请求防护</h3>
                <p class="shield-card-desc">这里处理脚本访问、空 UA、搜索引擎放行和基础 WAF 等面向绝大多数站点的通用能力。</p>
            </div>
            <div class="shield-list">
                <?php foreach ([
                    ['allowSpiders', '搜索引擎放行', '对已支持的 Google、Bing、百度蜘蛛执行双向验证后放行，避免影响 SEO。'],
                    ['denyEmptyUa', '拦截空 UA', '空 User-Agent 通常来自脚本或扫描器，正常浏览器基本都会带 UA。'],
                    ['blockScriptUa', '拦截脚本 UA', '基于 UA 关键字识别 curl、wget、python-requests、Go-http-client 等常见脚本访问。'],
                    ['denyBadMethods', '拦截异常方法', '拦截非常规或明显异常的 HTTP 方法。'],
                    ['wafEnable', '启用基础 WAF', '对注入、路径穿越、协议异常、已知探测路径等特征做基础识别。'],
                ] as [$key, $title, $desc]): ?>
                    <div class="shield-list-item">
                        <div class="shield-list-item-meta">
                            <h4 class="shield-list-item-title"><?php echo Text::e($title); ?></h4>
                            <p class="shield-list-item-desc"><?php echo Text::e($desc); ?></p>
                        </div>
                        <div class="shield-list-item-control">
                            <label class="shield-switch">
                                <input type="checkbox" name="<?php echo Text::e($key); ?>" value="1"<?php echo $settings[$key] === '1' ? ' checked' : ''; ?>>
                                <span class="shield-slider"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="shield-card">
            <div class="shield-card-header">
                <h3 class="shield-card-title">浏览器一致性识别</h3>
                <p class="shield-card-desc">用于识别声明为浏览器但请求特征异常的访问。</p>
            </div>
            <div class="shield-list">
                <?php foreach ([
                    ['browserCheck', '浏览器最低版本要求', '声称是浏览器却使用过低版本时按风险策略处理。'],
                    ['secFetchCheck', 'Sec-Fetch 校验', '对声称是浏览器但缺少 Sec-Fetch 头的页面请求追加风险判断。'],
                    ['headerCompleteness', '浏览器基础头完整度', '检查 Accept、Accept-Language、Accept-Encoding 三项基础头。'],
                    ['httpVersionCheck', 'HTTP/1.x 风险识别', '将异常的 HTTP/1.x 请求作为附加风险信号参与判断。'],
                    ['blockProxy', '代理头识别', '检测未受信来源携带的代理头信息。'],
                ] as [$key, $title, $desc]): ?>
                    <div class="shield-list-item">
                        <div class="shield-list-item-meta">
                            <h4 class="shield-list-item-title"><?php echo Text::e($title); ?></h4>
                            <p class="shield-list-item-desc"><?php echo Text::e($desc); ?></p>
                        </div>
                        <div class="shield-list-item-control">
                            <label class="shield-switch">
                                <input type="checkbox" name="<?php echo Text::e($key); ?>" value="1"<?php echo $settings[$key] === '1' ? ' checked' : ''; ?>>
                                <span class="shield-slider"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="shield-block-item">
                    <div class="shield-list-item-meta">
                        <h4 class="shield-list-item-title">最低浏览器版本</h4>
                        <p class="shield-list-item-desc">仅在启用“浏览器最低版本要求”时参与判断。</p>
                    </div>
                    <div class="shield-matrix">
                        <label class="shield-field">
                            <span>Chrome</span>
                            <input type="number" name="minChrome" min="1" max="300" value="<?php echo (int) $settings['minChrome']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>Firefox</span>
                            <input type="number" name="minFirefox" min="1" max="300" value="<?php echo (int) $settings['minFirefox']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>Edge</span>
                            <input type="number" name="minEdge" min="1" max="300" value="<?php echo (int) $settings['minEdge']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>Safari</span>
                            <input type="number" name="minSafari" min="1" max="100" value="<?php echo (int) $settings['minSafari']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field shield-field-wide">
                            <span>蜘蛛验证缓存小时</span>
                            <input type="number" name="spiderCacheHours" min="1" max="168" value="<?php echo (int) $settings['spiderCacheHours']; ?>" class="shield-input">
                        </label>
                    </div>
                </div>

                <div class="shield-block-item">
                    <div class="shield-list-item-meta">
                        <h4 class="shield-list-item-title">受信代理与 XML-RPC 白名单</h4>
                        <p class="shield-list-item-desc">填写受信代理或固定客户端的 IP / CIDR；留空表示不额外放行。</p>
                    </div>
                    <div class="shield-grid-2 shield-grid-pad">
                        <div class="shield-stack">
                            <label class="shield-field">
                                <span>受信代理 IP / CIDR</span>
                                <textarea name="proxyTrusted" class="shield-input mono" rows="5"><?php echo Text::e((string) $settings['proxyTrusted']); ?></textarea>
                            </label>
                        </div>
                        <div class="shield-stack">
                            <label class="shield-field">
                                <span>XML-RPC 白名单 IP / CIDR</span>
                                <textarea name="xmlrpcAllowlist" class="shield-input mono" rows="5"><?php echo Text::e((string) $settings['xmlrpcAllowlist']); ?></textarea>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="shield-card">
            <div class="shield-card-header">
                <h3 class="shield-card-title">名单与陷阱</h3>
                <p class="shield-card-desc">白名单优先处理；命中陷阱路径的请求会按扫描行为记录并处理。</p>
            </div>
            <div class="shield-list">
                <div class="shield-grid-2 shield-grid-pad">
                    <div class="shield-stack">
                        <label class="shield-field">
                            <span>IP 白名单</span>
                            <textarea name="ipAllowlist" class="shield-input mono" rows="6"><?php echo Text::e((string) $settings['ipAllowlist']); ?></textarea>
                        </label>
                    </div>
                    <div class="shield-stack">
                        <label class="shield-field">
                            <span>IP 黑名单</span>
                            <textarea name="ipDenylist" class="shield-input mono" rows="6"><?php echo Text::e((string) $settings['ipDenylist']); ?></textarea>
                        </label>
                    </div>
                    <div class="shield-stack">
                        <label class="shield-field">
                            <span>UA 白名单</span>
                            <textarea name="uaAllowlist" class="shield-input mono" rows="6"><?php echo Text::e((string) $settings['uaAllowlist']); ?></textarea>
                        </label>
                    </div>
                    <div class="shield-stack">
                        <label class="shield-field">
                            <span>UA 黑名单</span>
                            <textarea name="uaDenylist" class="shield-input mono" rows="6"><?php echo Text::e((string) $settings['uaDenylist']); ?></textarea>
                        </label>
                    </div>
                </div>
                <div class="shield-block-item">
                    <div class="shield-list-item-meta">
                        <h4 class="shield-list-item-title">扫描器陷阱路径</h4>
                            <p class="shield-list-item-desc">真实用户几乎不会访问这些路径，命中后会直接计入恶意行为并触发封禁。支持使用 `*` 表示通配。</p>
                    </div>
                    <textarea name="trapPaths" class="shield-input mono" rows="8"><?php echo Text::e((string) $settings['trapPaths']); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="tr-panel-pane<?php echo $currentTab === 'challenge' ? ' is-active' : ''; ?>" data-tab="challenge">
        <div class="shield-card">
            <div class="shield-card-header">
                <h3 class="shield-card-title">挑战与限频</h3>
                <p class="shield-card-desc">用于配置普通页面、登录、评论和 XML-RPC 请求的限频与挑战规则。</p>
            </div>
            <div class="shield-list">
                <div class="shield-block-item">
                    <div class="shield-list-item-meta">
                        <h4 class="shield-list-item-title">限频阈值</h4>
                        <p class="shield-list-item-desc">单位分别为秒和次数，用于控制各类请求的触发阈值。</p>
                    </div>
                    <div class="shield-matrix">
                        <label class="shield-field">
                            <span>站点窗口</span>
                            <input type="number" name="generalWindow" min="10" max="3600" value="<?php echo (int) $settings['generalWindow']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>站点次数</span>
                            <input type="number" name="generalLimit" min="1" max="10000" value="<?php echo (int) $settings['generalLimit']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>登录窗口</span>
                            <input type="number" name="loginWindow" min="60" max="86400" value="<?php echo (int) $settings['loginWindow']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>登录次数</span>
                            <input type="number" name="loginLimit" min="1" max="200" value="<?php echo (int) $settings['loginLimit']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>评论窗口</span>
                            <input type="number" name="commentWindow" min="10" max="3600" value="<?php echo (int) $settings['commentWindow']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>评论次数</span>
                            <input type="number" name="commentLimit" min="1" max="200" value="<?php echo (int) $settings['commentLimit']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>XML-RPC 窗口</span>
                            <input type="number" name="xmlrpcWindow" min="10" max="86400" value="<?php echo (int) $settings['xmlrpcWindow']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>XML-RPC 次数</span>
                            <input type="number" name="xmlrpcLimit" min="1" max="200" value="<?php echo (int) $settings['xmlrpcLimit']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>恶意累计阈值</span>
                            <input type="number" name="badLimit" min="1" max="100" value="<?php echo (int) $settings['badLimit']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>挑战等待秒数</span>
                            <input type="number" name="challengeWait" min="0" max="30" value="<?php echo (int) $settings['challengeWait']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>自动封禁小时</span>
                            <input type="number" name="autoBanHours" min="1" max="720" value="<?php echo (int) $settings['autoBanHours']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>陷阱封禁小时</span>
                            <input type="number" name="trapBanHours" min="1" max="720" value="<?php echo (int) $settings['trapBanHours']; ?>" class="shield-input">
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="shield-card">
            <div class="shield-card-header">
                <h3 class="shield-card-title">评论与上传保护</h3>
                <p class="shield-card-desc">用于配置评论提交和上传请求的基础保护规则。</p>
            </div>
            <div class="shield-list">
                <?php foreach ([
                    ['commentRequireChallenge', '评论请求需要先挑战', '非登录用户评论在命中高风险条件时需要先完成基础验证。'],
                    ['uploadDoubleExt', '拦截双扩展上传', '例如 `test.php.jpg` 这类双扩展文件会被直接拦截。'],
                    ['uploadScan', '扫描上传内容特征', '对上传内容做轻量样本检测，识别脚本片段与高风险标记。'],
                ] as [$key, $title, $desc]): ?>
                    <div class="shield-list-item">
                        <div class="shield-list-item-meta">
                            <h4 class="shield-list-item-title"><?php echo Text::e($title); ?></h4>
                            <p class="shield-list-item-desc"><?php echo Text::e($desc); ?></p>
                        </div>
                        <div class="shield-list-item-control">
                            <label class="shield-switch">
                                <input type="checkbox" name="<?php echo Text::e($key); ?>" value="1"<?php echo $settings[$key] === '1' ? ' checked' : ''; ?>>
                                <span class="shield-slider"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="shield-block-item">
                    <div class="shield-matrix">
                        <label class="shield-field">
                            <span>评论最短秒数</span>
                            <input type="number" name="commentMinSeconds" min="0" max="60" value="<?php echo (int) $settings['commentMinSeconds']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>评论最大链接数</span>
                            <input type="number" name="commentLinks" min="0" max="50" value="<?php echo (int) $settings['commentLinks']; ?>" class="shield-input">
                        </label>
                        <label class="shield-field">
                            <span>上传大小上限 KB</span>
                            <input type="number" name="uploadMaxKb" min="0" max="102400" value="<?php echo (int) $settings['uploadMaxKb']; ?>" class="shield-input">
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tr-panel-pane<?php echo $currentTab === 'access' ? ' is-active' : ''; ?>" data-tab="access">
        <div class="shield-card">
            <div class="shield-card-header">
                <h3 class="shield-card-title">受限访问规则</h3>
                <p class="shield-card-desc">命中后会直接中断原页面输出，适合会员内容、内部页面或需要登录后查看的资源。</p>
            </div>
            <div class="shield-list">
                <div class="shield-block-item">
                    <?php if (!empty($accessSummary['rules'])): ?>
                        <div class="shield-compact-list">
                            <?php foreach ((array) $accessSummary['rules'] as $item): ?>
                                <div class="shield-compact-item">
                                    <strong>第 <?php echo (int) ($item['line'] ?? 0); ?> 行</strong>
                                    <span><?php echo Text::e((string) ($item['summary'] ?? '')); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (($accessSummary['issueCount'] ?? 0) === 0): ?>
                        <p class="shield-list-item-desc">当前未配置访问规则。配置完成后，这里会显示规则摘要，便于快速核对。</p>
                    <?php endif; ?>
                    <?php if (!empty($accessSummary['issues'])): ?>
                        <div class="shield-issue-list">
                            <?php foreach ((array) $accessSummary['issues'] as $issue): ?>
                                <div class="shield-issue-item"><?php echo Text::e((string) $issue); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="shield-block-item">
                    <div class="shield-list-item-meta">
                        <h4 class="shield-list-item-title">规则列表</h4>
                        <p class="shield-list-item-desc">按书写顺序匹配，命中即停止。格式：匹配对象 =&gt; 需要权限 =&gt; 处理方式。</p>
                    </div>
                    <div class="shield-access-guide">
                        <div class="shield-access-guide-item">
                            <strong>匹配对象</strong>
                            <p class="shield-access-guide-desc">支持路径和内容实体。</p>
                            <div class="shield-access-guide-tags">
                                <span class="shield-access-guide-tag">/member/*</span>
                                <span class="shield-access-guide-tag">slug:vip</span>
                                <span class="shield-access-guide-tag">cid:123</span>
                                <span class="shield-access-guide-tag">category:private</span>
                                <span class="shield-access-guide-tag">tag:members</span>
                                <span class="shield-access-guide-tag">type:post</span>
                            </div>
                        </div>
                        <div class="shield-access-guide-item">
                            <strong>需要权限</strong>
                            <p class="shield-access-guide-desc">支持登录态和角色写法。</p>
                            <div class="shield-access-guide-tags">
                                <span class="shield-access-guide-tag">login</span>
                                <span class="shield-access-guide-tag">role:subscriber</span>
                                <span class="shield-access-guide-tag">role:editor|administrator</span>
                            </div>
                        </div>
                        <div class="shield-access-guide-item">
                            <strong>处理方式</strong>
                            <p class="shield-access-guide-desc">支持 HTML、403 和跳转。</p>
                            <div class="shield-access-guide-tags">
                                <span class="shield-access-guide-tag">html</span>
                                <span class="shield-access-guide-tag">403</span>
                                <span class="shield-access-guide-tag">redirect</span>
                                <span class="shield-access-guide-tag">redirect:/login.php</span>
                            </div>
                        </div>
                    </div>
                    <textarea
                        name="accessRules"
                        class="shield-input mono"
                        rows="10"
                        placeholder="/member/* => login => html&#10;slug:vip => role:subscriber => redirect&#10;cid:123 => role:editor|administrator => 403"
                    ><?php echo Text::e((string) $settings['accessRules']); ?></textarea>
                </div>
                <div class="shield-grid-2 shield-grid-pad">
                    <div class="shield-stack">
                        <label class="shield-field">
                            <span>默认提示 HTML</span>
                            <textarea name="accessHtml" class="shield-input mono" rows="8"><?php echo Text::e((string) $settings['accessHtml']); ?></textarea>
                        </label>
                    </div>
                    <div class="shield-stack">
                        <label class="shield-field">
                            <span>默认跳转地址</span>
                            <input type="text" name="accessRedirect" value="<?php echo Text::e((string) $settings['accessRedirect']); ?>" class="shield-input">
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="renewshield-sticky" class="tr-panel-sticky">
        <div class="shield-sticky-actions">
            <button type="submit" class="btn primary">保存当前配置</button>
        </div>
    </div>
</form>
