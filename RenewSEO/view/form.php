<?php
declare(strict_types=1);

use TypechoPlugin\RenewSEO\Settings;
use TypechoPlugin\RenewSEO\Text;
?>
<form id="renewseo-main-form" class="renewseo-form" method="post" action="<?php echo Text::e(Settings::actionUrl('save', true)); ?>">
    <div class="tr-panel-pane is-active" data-tab="global">
        <div class="renewseo-card">
            <div class="renewseo-card-header">
                <h3 class="renewseo-card-title">核心系统调度</h3>
            </div>
            <div class="renewseo-list">
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">插件启用</h4>
                        <p class="renewseo-list-item-desc">全局开启或暂停 RenewSEO 的所有功能。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="enabled" value="1"<?php echo $settings['enabled'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">日志自动清理</h4>
                        <p class="renewseo-list-item-desc">自动删除超期的运行日志与 404 记录。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="logAutoClean" value="1"<?php echo $settings['logAutoClean'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">异步推送</h4>
                        <p class="renewseo-list-item-desc">采用异步请求方式执行已启用的搜索引擎推送任务，以缩短保存响应时间。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="pushAsync" value="1"<?php echo $settings['pushAsync'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">面板每页数量</h4>
                        <p class="renewseo-list-item-desc">控制运行日志与 404 记录列表的单页显示数量。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="panelSize" min="10" max="200" value="<?php echo (int) $settings['panelSize']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">配置缓存时长（秒）</h4>
                        <p class="renewseo-list-item-desc">用于控制配置读取缓存时长，以减少频繁查询。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="cacheTtl" min="60" max="3600" value="<?php echo (int) $settings['cacheTtl']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">推送超时秒数</h4>
                        <p class="renewseo-list-item-desc">搜索引擎推送 API 请求的最大等待时间。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="pushTimeout" min="2" max="20" value="<?php echo (int) $settings['pushTimeout']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">日志保留天数</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="logKeepDays" min="0" max="3650" value="<?php echo (int) $settings['logKeepDays']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">404 保留天数</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="notFoundKeepDays" min="0" max="3650" value="<?php echo (int) $settings['notFoundKeepDays']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用 404 监控</h4>
                        <p class="renewseo-list-item-desc">记录缺失页面路径与命中次数，用于发现失效链接。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="notFoundEnable" value="1"<?php echo $settings['notFoundEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">404 访问 IP 脱敏</h4>
                        <p class="renewseo-list-item-desc">记录 404 时对访问 IP 进行脱敏处理，仅保留统计所需信息。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="notFoundMaskIp" value="1"<?php echo $settings['notFoundMaskIp'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">记录来源地址</h4>
                        <p class="renewseo-list-item-desc">默认关闭，避免把外部来源地址长期保存在后台。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="notFoundStoreReferer" value="1"<?php echo $settings['notFoundStoreReferer'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">记录访问标识（UA）</h4>
                        <p class="renewseo-list-item-desc">默认关闭，仅在分析特定爬虫或异常访问来源时启用。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="notFoundStoreUa" value="1"<?php echo $settings['notFoundStoreUa'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tr-panel-pane" data-tab="spiders">
        <div class="renewseo-card">
            <div class="renewseo-card-header">
                <h3 class="renewseo-card-title">Robots 控制</h3>
            </div>
            <div class="renewseo-list">
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用 Robots</h4>
                        <p class="renewseo-list-item-desc">在站点根目录写入 robots.txt 文件，用于指引搜索引擎爬虫。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="robotsEnable" value="1"<?php echo $settings['robotsEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">自动写入 Sitemap 地址</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="robotsSitemap" value="1"<?php echo $settings['robotsSitemap'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">未配置蜘蛛默认策略</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <select name="robotsDefault" class="renewseo-input w-short">
                            <option value="allow"<?php echo $settings['robotsDefault'] === 'allow' ? ' selected' : ''; ?>>允许</option>
                            <option value="deny"<?php echo $settings['robotsDefault'] === 'deny' ? ' selected' : ''; ?>>拒绝</option>
                        </select>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">目录限制范围</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <select name="robotsMode" class="renewseo-input w-medium">
                            <option value="default_only"<?php echo $settings['robotsMode'] === 'default_only' ? ' selected' : ''; ?>>仅作用于未配置蜘蛛</option>
                            <option value="all"<?php echo $settings['robotsMode'] === 'all' ? ' selected' : ''; ?>>作用于所有蜘蛛</option>
                        </select>
                    </div>
                </div>
                
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">显式允许蜘蛛</h4>
                        <p class="renewseo-list-item-desc">每行一个蜘蛛名称，如 Baiduspider。</p>
                    </div>
                    <textarea class="renewseo-input w-full" name="robotsAllowed" rows="4"><?php echo Text::e($settings['robotsAllowed']); ?></textarea>
                </div>
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">显式拒绝蜘蛛</h4>
                    </div>
                    <textarea class="renewseo-input w-full" name="robotsDenied" rows="4"><?php echo Text::e($settings['robotsDenied']); ?></textarea>
                </div>
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">限制目录</h4>
                        <p class="renewseo-list-item-desc">如 /admin/ 等，每行一个。</p>
                    </div>
                    <textarea class="renewseo-input w-full" name="robotsBlocked" rows="4"><?php echo Text::e($settings['robotsBlocked']); ?></textarea>
                </div>
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">附加 Sitemap 地址</h4>
                    </div>
                    <textarea class="renewseo-input w-full" name="robotsCustomSitemaps" rows="3"><?php echo Text::e($settings['robotsCustomSitemaps']); ?></textarea>
                </div>
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">附加 Robots 规则</h4>
                    </div>
                    <textarea class="renewseo-input w-full" name="robotsExtra" rows="4"><?php echo Text::e($settings['robotsExtra']); ?></textarea>
                </div>
                
                <div class="renewseo-preview">
                    <strong>Robots.txt 预览</strong>
                    <pre><?php echo Text::e($robotsPreview); ?></pre>
                </div>
            </div>
        </div>

        <div class="renewseo-card">
            <div class="renewseo-card-header">
                <h3 class="renewseo-card-title">Sitemap 生成</h3>
            </div>
            <div class="renewseo-list">
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用 Sitemap</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="sitemapEnable" value="1"<?php echo $settings['sitemapEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">生成 TXT 格式</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="sitemapTxt" value="1"<?php echo $settings['sitemapTxt'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">包含文章 (Post)</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="sitemapPost" value="1"<?php echo $settings['sitemapPost'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">包含页面 (Page)</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="sitemapPage" value="1"<?php echo $settings['sitemapPage'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">包含分类 (Category)</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="sitemapCategory" value="1"<?php echo $settings['sitemapCategory'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">包含标签 (Tag)</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="sitemapTag" value="1"<?php echo $settings['sitemapTag'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">包含作者页 (Author)</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="sitemapAuthor" value="1"<?php echo $settings['sitemapAuthor'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">补充图片信息</h4>
                        <p class="renewseo-list-item-desc">在现有 sitemap.xml 中为文章和页面补充图片地址信息</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="sitemapImage" value="1"<?php echo $settings['sitemapImage'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">自动重建间隔（秒）</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="sitemapDebounce" min="0" max="3600" value="<?php echo (int) $settings['sitemapDebounce']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">每个 XML 最大 URL 数</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="sitemapSplit" min="100" max="50000" value="<?php echo (int) $settings['sitemapSplit']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">权重与频率配置</h4>
                    </div>
                    <div class="renewseo-matrix">
                        <label class="renewseo-field">
                            <span>首页权重</span>
                            <input type="text" name="sitemapPriorityHome" value="<?php echo Text::e($settings['sitemapPriorityHome']); ?>" class="renewseo-input w-full">
                        </label>
                        <label class="renewseo-field">
                            <span>文章权重</span>
                            <input type="text" name="sitemapPriorityPost" value="<?php echo Text::e($settings['sitemapPriorityPost']); ?>" class="renewseo-input w-full">
                        </label>
                        <label class="renewseo-field">
                            <span>页面权重</span>
                            <input type="text" name="sitemapPriorityPage" value="<?php echo Text::e($settings['sitemapPriorityPage']); ?>" class="renewseo-input w-full">
                        </label>
                        <label class="renewseo-field">
                            <span>分类权重</span>
                            <input type="text" name="sitemapPriorityCategory" value="<?php echo Text::e($settings['sitemapPriorityCategory']); ?>" class="renewseo-input w-full">
                        </label>
                        <label class="renewseo-field">
                            <span>标签权重</span>
                            <input type="text" name="sitemapPriorityTag" value="<?php echo Text::e($settings['sitemapPriorityTag']); ?>" class="renewseo-input w-full">
                        </label>
                        <label class="renewseo-field">
                            <span>作者权重</span>
                            <input type="text" name="sitemapPriorityAuthor" value="<?php echo Text::e($settings['sitemapPriorityAuthor']); ?>" class="renewseo-input w-full">
                        </label>
                        
                        <label class="renewseo-field">
                            <span>首页频率</span>
                            <select name="sitemapFreqHome" class="renewseo-input w-full">
                                <?php foreach ($freqOptions as $item): ?>
                                    <option value="<?php echo $item; ?>"<?php echo $item === $settings['sitemapFreqHome'] ? ' selected' : ''; ?>><?php echo $item; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="renewseo-field">
                            <span>文章频率</span>
                            <select name="sitemapFreqPost" class="renewseo-input w-full">
                                <?php foreach ($freqOptions as $item): ?>
                                    <option value="<?php echo $item; ?>"<?php echo $item === $settings['sitemapFreqPost'] ? ' selected' : ''; ?>><?php echo $item; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="renewseo-field">
                            <span>页面频率</span>
                            <select name="sitemapFreqPage" class="renewseo-input w-full">
                                <?php foreach ($freqOptions as $item): ?>
                                    <option value="<?php echo $item; ?>"<?php echo $item === $settings['sitemapFreqPage'] ? ' selected' : ''; ?>><?php echo $item; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="renewseo-field">
                            <span>分类/标签频率</span>
                            <select name="sitemapFreqTaxonomy" class="renewseo-input w-full">
                                <?php foreach ($freqOptions as $item): ?>
                                    <option value="<?php echo $item; ?>"<?php echo $item === $settings['sitemapFreqTaxonomy'] ? ' selected' : ''; ?>><?php echo $item; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tr-panel-pane" data-tab="page">
        <div class="renewseo-card">
            <div class="renewseo-card-header">
                <h3 class="renewseo-card-title">页面元信息与规范化</h3>
            </div>
            <div class="renewseo-list">
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用 Canonical 规范化</h4>
                        <p class="renewseo-list-item-desc">仅控制 RenewSEO 提供的 Canonical 规范化逻辑，不影响程序内核默认输出。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="canonicalEnable" value="1"<?php echo $settings['canonicalEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">使用 RenewSEO 输出社交摘要标签</h4>
                        <p class="renewseo-list-item-desc">开启后由 RenewSEO 输出 Open Graph 与 Twitter 卡片标签；关闭后回退到程序默认输出。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="ogEnable" value="1"<?php echo $settings['ogEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用时间因子</h4>
                        <p class="renewseo-list-item-desc">为文章与独立页面输出字节跳动时间 Meta 与百度时间结构化数据。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="timeEnable" value="1"<?php echo $settings['timeEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">搜索页 noindex</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="noindexSearch" value="1"<?php echo $settings['noindexSearch'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">404 页 noindex</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="noindex404" value="1"<?php echo $settings['noindex404'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">分类页 noindex</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="noindexCategory" value="1"<?php echo $settings['noindexCategory'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">标签页 noindex</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="noindexTag" value="1"<?php echo $settings['noindexTag'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">作者页 noindex</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="noindexAuthor" value="1"<?php echo $settings['noindexAuthor'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">分页归档 noindex</h4>
                        <p class="renewseo-list-item-desc">对第 2 页及之后的归档分页输出 noindex,follow。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="noindexPaged" value="1"<?php echo $settings['noindexPaged'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">自动补全图片 Alt</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="altEnable" value="1"<?php echo $settings['altEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">默认社交摘要图片</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="text" name="ogDefaultImage" value="<?php echo Text::e($settings['ogDefaultImage']); ?>" placeholder="https://example.com/og.jpg" class="renewseo-input w-medium">
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">Alt 模板</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="text" name="altTemplate" value="<?php echo Text::e($settings['altTemplate']); ?>" placeholder="{title} - {site}" class="renewseo-input w-medium">
                    </div>
                </div>
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">Canonical 要剔除的参数</h4>
                        <p class="renewseo-list-item-desc">每行一个，支持通配符如 utm_*</p>
                    </div>
                    <textarea class="renewseo-input w-full" name="canonicalStrip" rows="4"><?php echo Text::e($settings['canonicalStrip']); ?></textarea>
                </div>
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">Canonical 规范化规则</h4>
                        <p class="renewseo-list-item-desc">用于统一站点 Host、分页参数与尾斜杠形式。</p>
                    </div>
                    <div class="renewseo-matrix">
                        <div class="renewseo-field">
                            <span>统一站点 Host</span>
                            <label class="renewseo-switch">
                                <input type="checkbox" name="canonicalHost" value="1"<?php echo $settings['canonicalHost'] === '1' ? ' checked' : ''; ?>>
                                <span class="renewseo-slider"></span>
                            </label>
                        </div>
                        <div class="renewseo-field">
                            <span>去除 ?page=1</span>
                            <label class="renewseo-switch">
                                <input type="checkbox" name="canonicalPageOne" value="1"<?php echo $settings['canonicalPageOne'] === '1' ? ' checked' : ''; ?>>
                                <span class="renewseo-slider"></span>
                            </label>
                        </div>
                        <label class="renewseo-field">
                            <span>尾斜杠策略</span>
                            <select name="canonicalTrailingSlash" class="renewseo-input w-full">
                                <option value="keep"<?php echo $settings['canonicalTrailingSlash'] === 'keep' ? ' selected' : ''; ?>>保持当前</option>
                                <option value="add"<?php echo $settings['canonicalTrailingSlash'] === 'add' ? ' selected' : ''; ?>>统一补尾斜杠</option>
                                <option value="remove"<?php echo $settings['canonicalTrailingSlash'] === 'remove' ? ' selected' : ''; ?>>统一去尾斜杠</option>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="renewseo-block-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">结构化数据</h4>
                        <p class="renewseo-list-item-desc">按需输出文章、面包屑与站点搜索相关的结构化数据。</p>
                    </div>
                    <div class="renewseo-matrix">
                        <div class="renewseo-field">
                            <span>文章结构化数据</span>
                            <label class="renewseo-switch">
                                <input type="checkbox" name="schemaArticle" value="1"<?php echo $settings['schemaArticle'] === '1' ? ' checked' : ''; ?>>
                                <span class="renewseo-slider"></span>
                            </label>
                        </div>
                        <div class="renewseo-field">
                            <span>面包屑结构化数据</span>
                            <label class="renewseo-switch">
                                <input type="checkbox" name="schemaBreadcrumb" value="1"<?php echo $settings['schemaBreadcrumb'] === '1' ? ' checked' : ''; ?>>
                                <span class="renewseo-slider"></span>
                            </label>
                        </div>
                        <div class="renewseo-field">
                            <span>站点搜索结构化数据</span>
                            <label class="renewseo-switch">
                                <input type="checkbox" name="schemaWebsiteSearch" value="1"<?php echo $settings['schemaWebsiteSearch'] === '1' ? ' checked' : ''; ?>>
                                <span class="renewseo-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tr-panel-pane" data-tab="push">
        <div class="renewseo-card">
            <div class="renewseo-card-header">
                <h3 class="renewseo-card-title">百度搜索推送</h3>
            </div>
            <div class="renewseo-list">
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用百度普通推送</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" id="baiduEnable" name="baiduEnable" value="1"<?php echo $settings['baiduEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item group-baidu-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用百度快速收录</h4>
                        <p class="renewseo-list-item-desc">用于已开通快速收录权限的页面；未满足条件时将回退为普通推送。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="baiduQuick" value="1"<?php echo $settings['baiduQuick'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item group-baidu-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">编辑内容时同步推送</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="baiduPushOnEdit" value="1"<?php echo $settings['baiduPushOnEdit'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item group-baidu-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">仅推送指定天数内内容</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="baiduDays" min="0" max="3650" value="<?php echo (int) $settings['baiduDays']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                <div class="renewseo-block-item group-baidu-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">百度 Token</h4>
                    </div>
                    <input type="text" name="baiduToken" value="<?php echo Text::e($settings['baiduToken']); ?>" class="renewseo-input w-full">
                </div>
            </div>
        </div>

        <div class="renewseo-card">
            <div class="renewseo-card-header">
                <h3 class="renewseo-card-title">IndexNow 推送</h3>
            </div>
            <div class="renewseo-list">
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用 IndexNow</h4>
                        <p class="renewseo-list-item-desc">按 IndexNow 协议向支持该协议的搜索引擎提交更新通知。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" id="indexNowEnable" name="indexNowEnable" value="1"<?php echo $settings['indexNowEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item group-indexnow-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">编辑内容时同步推送</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="indexNowOnEdit" value="1"<?php echo $settings['indexNowOnEdit'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-block-item group-indexnow-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">IndexNow 密钥</h4>
                        <p class="renewseo-list-item-desc">插件会在站点根目录生成并维护 `<key>.txt` 验证文件。</p>
                    </div>
                    <input type="text" name="indexNowKey" value="<?php echo Text::e($settings['indexNowKey']); ?>" class="renewseo-input w-full">
                </div>
            </div>
        </div>

        <div class="renewseo-card">
            <div class="renewseo-card-header">
                <h3 class="renewseo-card-title">Bing 搜索推送</h3>
            </div>
            <div class="renewseo-list">
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用 Bing 推送</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" id="bingEnable" name="bingEnable" value="1"<?php echo $settings['bingEnable'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-list-item group-bing-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">编辑内容时同步推送</h4>
                    </div>
                    <div class="renewseo-list-item-control">
                        <label class="renewseo-switch">
                            <input type="checkbox" name="bingOnEdit" value="1"<?php echo $settings['bingOnEdit'] === '1' ? ' checked' : ''; ?>>
                            <span class="renewseo-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="renewseo-block-item group-bing-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">Bing API 密钥</h4>
                    </div>
                    <input type="text" name="bingApiKey" value="<?php echo Text::e($settings['bingApiKey']); ?>" class="renewseo-input w-full">
                </div>
            </div>
        </div>
    </div>

</form>
