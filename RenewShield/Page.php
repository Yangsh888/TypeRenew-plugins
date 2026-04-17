<?php
declare(strict_types=1);

namespace TypechoPlugin\RenewShield;

use Typecho\Response;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Page
{
    public static function block(string $title, string $message, array $meta = []): never
    {
        $body = '<div class="renewshield-copy">'
            . '<p class="renewshield-main">' . Text::e($message) . '</p>'
            . '<p class="renewshield-sub">本次请求已在应用层被拦截，页面主体不会继续输出。</p>'
            . self::meta($meta)
            . '</div>';

        self::render(403, $title, $body);
    }

    public static function access(string $title, string $html, string $redirect = ''): never
    {
        $body = '<div class="renewshield-copy">' . $html;
        if (!str_contains($html, 'renewshield-btn')) {
            $body .= self::actions([
                self::link('/', '返回首页', true),
                $redirect !== '' ? self::link($redirect, '前往目标页') : '',
            ]);
        }

        $body .= '</div>';
        self::render(403, $title, $body);
    }

    public static function challenge(string $title, string $message, string $action, string $token, int $wait, array $meta = []): never
    {
        $body = '<div class="renewshield-copy">'
            . '<p class="renewshield-main">' . Text::e($message) . '</p>'
            . '<p class="renewshield-sub">当前请求需要完成基础验证，原请求尚未继续处理。</p>'
            . self::meta($meta)
            . '<div class="renewshield-verify">'
            . '<div class="renewshield-verify-state">'
            . '<strong id="renewshield-status">正在准备验证</strong>'
            . '<span id="renewshield-note">倒计时结束后，请勾选确认并继续访问。</span>'
            . '</div>'
            . '<form class="renewshield-form" method="post" action="' . Text::e($action) . '" id="renewshield-form">'
            . '<input type="hidden" name="token" value="' . Text::e($token) . '">'
            . '<label class="renewshield-check" for="renewshield-confirm">'
            . '<input type="checkbox" name="confirm" value="1" id="renewshield-confirm" disabled>'
            . '<span>我已确认当前请求由本人发起，并继续访问。</span>'
            . '</label>'
            . self::actions([
                '<button type="submit" class="renewshield-btn" id="renewshield-btn" disabled>等待验证就绪</button>',
                self::link('/', '返回首页', true),
            ])
            . '</form>'
            . '</div>'
            . '</div>'
            . '<script>(function(){var seconds=' . max(0, $wait) . ';var btn=document.getElementById("renewshield-btn");var checkbox=document.getElementById("renewshield-confirm");var status=document.getElementById("renewshield-status");var note=document.getElementById("renewshield-note");if(!btn||!checkbox||!status||!note){return;}function sync(){btn.disabled=!(seconds<=0&&checkbox.checked);}function tick(){if(seconds<=0){checkbox.disabled=false;status.textContent="请完成点击确认";note.textContent="勾选确认后，点击“通过基础验证并继续”即可返回原请求。";btn.textContent="通过基础验证并继续";sync();return;}status.textContent="请稍候";note.textContent="请等待 "+seconds+" 秒后再完成确认。";btn.textContent="请等待 "+seconds+" 秒";seconds-=1;window.setTimeout(tick,1000);}checkbox.addEventListener("change",sync);tick();})();</script>';

        self::render(403, $title, $body);
    }

    private static function meta(array $meta): string
    {
        if ($meta === []) {
            return '';
        }

        $items = [];
        foreach ($meta as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $items[] = '<li>' . Text::e($item) . '</li>';
            if (count($items) >= 4) {
                break;
            }
        }

        if ($items === []) {
            return '';
        }

        return '<ul class="renewshield-meta">' . implode('', $items) . '</ul>';
    }

    private static function actions(array $items): string
    {
        $items = array_values(array_filter($items, static fn($item): bool => trim((string) $item) !== ''));
        if ($items === []) {
            return '';
        }

        return '<div class="renewshield-actions">' . implode('', $items) . '</div>';
    }

    private static function link(string $href, string $text, bool $subtle = false): string
    {
        return '<a class="renewshield-btn' . ($subtle ? ' renewshield-btn-sub' : '') . '" href="'
            . Text::e($href)
            . '">'
            . Text::e($text)
            . '</a>';
    }

    private static function render(int $status, string $title, string $body): never
    {
        Response::getInstance()
            ->setStatus($status)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('X-RenewShield', 'active')
            ->sendHeaders();

        echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . Text::e($title) . '</title>'
            . '<style>'
            . ':root{--bg:#f3f6fb;--fg:#111827;--muted:#6b7280;--card:#fff;--line:#e5e7eb;--accent:#2563eb;--accent2:#1d4ed8;--radius:16px;--surface:#eef4ff}'
            . '@media (prefers-color-scheme: dark){:root{--bg:#0f172a;--fg:#f8fafc;--muted:#94a3b8;--card:#111827;--line:#1f2937;--accent:#60a5fa;--accent2:#3b82f6;--surface:#0b1220}}'
            . '*{box-sizing:border-box}'
            . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:radial-gradient(1200px 600px at 10% -10%,rgba(37,99,235,.12),transparent 55%),var(--bg);color:var(--fg);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}'
            . '.renewshield-shell{width:min(760px,100%);background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:0 20px 60px rgba(0,0,0,.12);overflow:hidden}'
            . '.renewshield-head{display:flex;gap:14px;align-items:center;padding:24px;border-bottom:1px solid var(--line)}'
            . '.renewshield-dot{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700}'
            . '.renewshield-title{margin:0;font-size:20px;line-height:1.25}'
            . '.renewshield-body{padding:24px}'
            . '.renewshield-copy{display:flex;flex-direction:column;gap:14px}'
            . '.renewshield-main{margin:0;font-size:18px;line-height:1.5;font-weight:700}'
            . '.renewshield-sub{margin:0;color:var(--muted);line-height:1.75}'
            . '.renewshield-meta{margin:0;padding-left:20px;color:var(--muted);line-height:1.75}'
            . '.renewshield-verify{padding:16px;border:1px solid var(--line);border-radius:14px;background:var(--surface)}'
            . '.renewshield-verify-state{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}'
            . '.renewshield-verify-state strong{font-size:15px;line-height:1.5}'
            . '.renewshield-verify-state span{color:var(--muted);line-height:1.7}'
            . '.renewshield-form{display:flex;flex-direction:column;gap:14px}'
            . '.renewshield-check{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:var(--card);cursor:pointer}'
            . '.renewshield-check input{margin-top:3px}'
            . '.renewshield-check span{line-height:1.7}'
            . '.renewshield-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}'
            . '.renewshield-btn{height:42px;padding:0 16px;border-radius:11px;border:1px solid transparent;background:linear-gradient(90deg,var(--accent),var(--accent2));color:#fff;text-decoration:none;font-weight:700;font-size:14px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}'
            . '.renewshield-btn-sub{background:transparent;border-color:var(--line);color:var(--fg)}'
            . '.renewshield-btn[disabled]{opacity:.58;cursor:not-allowed}'
            . '.renewshield-check input[disabled] + span{opacity:.58}'
            . '@media (max-width:640px){.renewshield-head,.renewshield-body{padding:18px}.renewshield-actions{flex-direction:column;align-items:stretch}.renewshield-btn{width:100%}}'
            . '</style>'
            . '</head><body><main class="renewshield-shell"><header class="renewshield-head"><span class="renewshield-dot">R</span><h1 class="renewshield-title">'
            . Text::e($title)
            . '</h1></header><section class="renewshield-body">'
            . $body
            . '</section></main></body></html>';
        exit;
    }
}
