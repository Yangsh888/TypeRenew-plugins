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
                        <p class="renewseo-list-item-desc">使用非阻塞请求将数据推送到各大搜索引擎，提升保存响应速度。</p>
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
                        <p class="renewseo-list-item-desc">控制 SEO 控制面板中显示的日志条数。</p>
                    </div>
                    <div class="renewseo-list-item-control">
                        <input type="number" name="panelSize" min="10" max="200" value="<?php echo (int) $settings['panelSize']; ?>" class="renewseo-input w-short">
                    </div>
                </div>
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">配置缓存秒数</h4>
                        <p class="renewseo-list-item-desc">从数据库加载配置的防抖时间，减轻查询压力。</p>
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
                        <p class="renewseo-list-item-desc">生成虚拟的 robots.txt 文件来指引爬虫。</p>
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
                        <h4 class="renewseo-list-item-title">自动重建防抖秒数</h4>
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
                <h3 class="renewseo-card-title">Meta / OG / Canonical</h3>
            </div>
            <div class="renewseo-list">
                <div class="renewseo-list-item">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">启用 Canonical</h4>
                        <p class="renewseo-list-item-desc">为页面添加规范化链接，防止权重分散。</p>
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
                        <h4 class="renewseo-list-item-title">启用 Open Graph (OG)</h4>
                        <p class="renewseo-list-item-desc">优化链接在社交媒体上的卡片展现。</p>
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
                        <p class="renewseo-list-item-desc">输出文章的发布与更新时间 meta，对百度收录友好。</p>
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
                        <h4 class="renewseo-list-item-title">默认 OG 图片</h4>
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
                        <p class="renewseo-list-item-desc">适合高时效、移动友好页面。</p>
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
                        <h4 class="renewseo-list-item-title">编辑内容也推送</h4>
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
                        <h4 class="renewseo-list-item-title">仅推送 N 天内内容</h4>
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
                        <p class="renewseo-list-item-desc">支持 Yandex, Bing 等协议的统一收录推送。</p>
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
                        <h4 class="renewseo-list-item-title">编辑内容也推送</h4>
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
                        <h4 class="renewseo-list-item-title">IndexNow Key</h4>
                    </div>
                    <input type="text" name="indexNowKey" value="<?php echo Text::e($settings['indexNowKey']); ?>" class="renewseo-input w-full">
                </div>
                <div class="renewseo-block-item group-indexnow-push">
                    <div class="renewseo-list-item-meta">
                        <h4 class="renewseo-list-item-title">Key 文件相对路径</h4>
                        <p class="renewseo-list-item-desc">留空表示站点根目录。</p>
                    </div>
                    <input type="text" name="indexNowKeyPath" value="<?php echo Text::e($settings['indexNowKeyPath']); ?>" placeholder="可留空或填如 seo/indexnow-key.txt" class="renewseo-input w-full">
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
                        <h4 class="renewseo-list-item-title">编辑内容也推送</h4>
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
                        <h4 class="renewseo-list-item-title">Bing API Key</h4>
                    </div>
                    <input type="text" name="bingApiKey" value="<?php echo Text::e($settings['bingApiKey']); ?>" class="renewseo-input w-full">
                </div>
            </div>
        </div>
    </div>

</form>
