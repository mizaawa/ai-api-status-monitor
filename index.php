<?php
/**
 * 前台首页 - AI 监控面板
 */
require_once __DIR__ . '/config.php';

if (!is_installed()) {
    header('Location: ' . site_url('install.php'));
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

ensure_group_icon_column();

$groups = get_groups_with_counts();
$homeContent = get_home_content();
$checkInterval = (int)get_setting('check_interval', '60');
$heroKicker = get_setting('hero_kicker', 'Live Model Status');
$heroTitle = get_setting('hero_title', 'Service Monitor');
$heroTitleParts = preg_split('/\s+/', trim($heroTitle), 2) ?: ['Service Monitor'];
$heroTitleFirst = $heroTitleParts[0] ?? 'Service';
$heroTitleSecond = $heroTitleParts[1] ?? 'Monitor';
$heroDescription = get_setting('hero_description', '黑白极简状态面板，实时读取模型接口可用性、对话延迟、端点 PING 与最近检测轨迹。');
$heroTags = array_values(array_filter(array_map('trim', explode(',', get_setting('hero_tags', 'Liquid Glass UI,Realtime Insight,Black / White')))));
if (empty($heroTags)) {
    $heroTags = ['Liquid Glass UI', 'Realtime Insight', 'Black / White'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= site_title() ?></title>
    <?= site_icon_tag() ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --ink: #050505;
            --ink-soft: #151515;
            --paper: #f7f7f4;
            --paper-deep: #ededE8;
            --muted: #737373;
            --line: rgba(5, 5, 5, 0.1);
            --glass: rgba(255, 255, 255, 0.58);
            --glass-strong: rgba(255, 255, 255, 0.76);
            --shadow: 0 28px 90px rgba(0, 0, 0, 0.12);
            --radius-xl: 38px;
            --radius-lg: 30px;
            --radius-md: 22px;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            min-height: 100vh;
            margin: 0;
            color: var(--ink);
            font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 18% 8%, rgba(255,255,255,0.96) 0, rgba(255,255,255,0.32) 18%, transparent 34%),
                radial-gradient(circle at 84% 12%, rgba(0,0,0,0.11) 0, rgba(0,0,0,0.045) 17%, transparent 34%),
                linear-gradient(145deg, #f9f9f6 0%, #ecece7 44%, #fafafa 100%);
            overflow-x: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            pointer-events: none;
            z-index: -1;
            filter: blur(6px);
            opacity: 0.72;
        }
        body::before {
            width: 520px;
            height: 520px;
            left: -160px;
            top: 90px;
            border-radius: 48% 52% 62% 38% / 42% 58% 44% 56%;
            background: linear-gradient(145deg, rgba(0,0,0,0.1), rgba(255,255,255,0.42));
        }
        body::after {
            width: 420px;
            height: 420px;
            right: -140px;
            bottom: 8%;
            border-radius: 62% 38% 42% 58% / 52% 42% 58% 48%;
            background: linear-gradient(145deg, rgba(255,255,255,0.72), rgba(0,0,0,0.08));
        }

        .noise {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: -1;
            opacity: 0.22;
            background-image:
                linear-gradient(rgba(0,0,0,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,0,0,0.02) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, black, transparent 88%);
        }

        .shell { max-width: 1480px; margin: 0 auto; padding: 24px; }
        @media (max-width: 640px) { .shell { padding: 14px; } }

        .glass {
            background: var(--glass);
            border: 1px solid rgba(255,255,255,0.78);
            box-shadow: var(--shadow), inset 0 1px 0 rgba(255,255,255,0.84);
            backdrop-filter: blur(28px) saturate(150%);
            -webkit-backdrop-filter: blur(28px) saturate(150%);
        }

        .liquid {
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }
        .liquid::before {
            content: "";
            position: absolute;
            inset: -1px;
            z-index: -1;
            border-radius: inherit;
            background:
                radial-gradient(circle at 16% 12%, rgba(255,255,255,0.82), transparent 28%),
                radial-gradient(circle at 82% 18%, rgba(0,0,0,0.08), transparent 30%),
                linear-gradient(135deg, rgba(255,255,255,0.5), rgba(255,255,255,0.12));
        }
        .liquid::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            right: -70px;
            bottom: -90px;
            z-index: -1;
            border-radius: 48% 52% 34% 66% / 58% 42% 58% 42%;
            background: rgba(0,0,0,0.045);
            filter: blur(1px);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.6);
            color: #111;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.72);
        }

        .brand-mark,
        .service-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            flex: 0 0 auto;
            background:
                radial-gradient(circle at 32% 25%, rgba(255,255,255,0.95), rgba(255,255,255,0.2) 32%, transparent 33%),
                linear-gradient(145deg, #111, #555 52%, #050505);
            color: white;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.35), 0 16px 32px rgba(0,0,0,0.2);
        }
        .brand-mark { width: 44px; height: 44px; border-radius: 18px; }
        .service-mark { width: 52px; height: 52px; border-radius: 999px; }
        .brand-mark svg,
        .service-mark svg { width: 56%; height: 56%; }

        .topbar {
            min-height: 76px;
            border-radius: 999px;
            padding: 14px 16px 14px 18px;
        }
        @media (max-width: 760px) {
            .topbar { border-radius: 30px; align-items: flex-start; }
        }

        .hero {
            border-radius: 48px;
            min-height: 360px;
            padding: clamp(28px, 5vw, 70px);
        }
        @media (max-width: 640px) {
            .hero { border-radius: 34px; min-height: auto; }
        }

        .kicker {
            letter-spacing: 0.36em;
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 800;
            color: rgba(0,0,0,0.58);
        }
        .hero-title {
            font-size: clamp(44px, 8vw, 112px);
            line-height: 0.88;
            letter-spacing: -0.08em;
            font-weight: 900;
        }
        .hero-title span {
            display: inline-block;
            padding-right: 0.08em;
        }

        .stat-card {
            border-radius: 999px;
            padding: 16px 22px;
            min-width: 156px;
            display: flex;
            flex-direction: column;
            background: rgba(255,255,255,0.66);
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.8), 0 16px 36px rgba(0,0,0,0.08);
        }
        .stat-number { font-variant-numeric: tabular-nums; letter-spacing: -0.06em; }
        .stat-card .stat-number { margin-top: auto; }
        .stat-total { color: #475569; }
        .stat-healthy { color: #16a34a; text-shadow: 0 10px 28px rgba(22,163,74,0.14); }
        .stat-attention { color: #dc2626; text-shadow: 0 10px 28px rgba(220,38,38,0.12); }
        .value-good { color: #16a34a; }
        .value-warn { color: #d97706; }
        .value-bad { color: #dc2626; }
        .value-muted { color: rgba(15,23,42,0.36); }

        .home-content {
            border-radius: var(--radius-lg);
            color: #171717;
        }

        .group-card {
            border-radius: 42px;
            overflow: hidden;
        }
        .group-header {
            padding: 22px;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            background: rgba(255,255,255,0.36);
        }
        .group-toggle {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: rgba(0,0,0,0.68);
            background: rgba(255,255,255,0.68);
            border: 1px solid rgba(0,0,0,0.09);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.9), 0 14px 30px rgba(0,0,0,0.08);
            transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
        }
        .group-toggle svg { transition: transform 0.18s ease; }
        .group-toggle[aria-expanded="true"] svg { transform: rotate(180deg); }
        .group-toggle:active { transform: scale(0.96); }
        .mobile-channel-dots {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            max-width: 100%;
        }
        .mini-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            box-shadow: 0 0 0 4px rgba(15,23,42,0.05), 0 8px 18px rgba(0,0,0,0.1);
        }
        .mini-status-dot.s-normal { background: #22c55e; }
        .mini-status-dot.s-slow { background: #f59e0b; }
        .mini-status-dot.s-error { background: #ef4444; }
        .mini-status-dot.s-unknown { background: rgba(15,23,42,0.22); }
        .group-card.mobile-collapsed .group-body { display: none; }
        .group-card.mobile-collapsed .group-header { border-bottom-color: transparent; }

        .channel-card {
            border-radius: 34px;
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease, background 0.22s ease;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.76);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.86), 0 18px 38px rgba(0,0,0,0.08);
            backdrop-filter: blur(22px) saturate(145%);
            -webkit-backdrop-filter: blur(22px) saturate(145%);
        }
        .channel-card:hover,
        .channel-card:focus,
        .channel-card:focus-within {
            transform: translateY(-5px);
            border-color: rgba(0,0,0,0.22);
            background: rgba(255,255,255,0.76);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.95), 0 28px 70px rgba(0,0,0,0.14);
            outline: none;
        }
        .channel-card:active { transform: translateY(-2px) scale(0.996); }

        .metric-card {
            border-radius: 26px;
            padding: 16px;
            background: rgba(255,255,255,0.62);
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.86);
        }
        .metric-label {
            font-size: 10px;
            line-height: 1;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-weight: 800;
            color: rgba(0,0,0,0.46);
        }
        .metric-value { font-variant-numeric: tabular-nums; }

        .status-strip {
            display: grid;
            grid-template-columns: repeat(48, minmax(0, 1fr));
            gap: 4px;
            align-items: stretch;
            width: 100%;
            max-width: 100%;
            height: 24px;
            overflow: visible;
            padding: 0;
            contain: layout;
        }
        .status-block {
            min-width: 0;
            width: 100%;
            border-radius: 999px;
            cursor: pointer;
            position: relative;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.34);
            transition: transform 0.14s ease, box-shadow 0.14s ease, filter 0.14s ease;
        }
        .status-block:hover,
        .status-block:focus {
            transform: translateY(-2px) scaleY(1.12);
            filter: brightness(1.05) saturate(1.08);
            box-shadow: 0 5px 14px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.5);
            z-index: 40;
            outline: none;
        }
        .status-block.s-normal  { background: linear-gradient(180deg, #34d399, #16a34a); }
        .status-block.s-slow    { background: linear-gradient(180deg, #facc15, #f59e0b); }
        .status-block.s-error   { background: linear-gradient(180deg, #fb7185, #dc2626); }
        .status-block.s-unknown { background: rgba(15,23,42,0.1); }

        .s-tip {
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: rgba(5,5,5,0.92);
            color: #fff;
            font-size: 11px;
            line-height: 1.55;
            padding: 7px 10px;
            border-radius: 999px;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            z-index: 50;
            transition: opacity 0.12s ease;
        }
        .status-block:hover .s-tip,
        .status-block:focus .s-tip { opacity: 1; }

        .refresh-countdown,
        .tabular { font-variant-numeric: tabular-nums; }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 5px rgba(34,197,94,0.14), 0 0 18px rgba(34,197,94,0.42);
        }
        .status-dot.is-checking {
            background: #f59e0b;
            box-shadow: 0 0 0 5px rgba(245,158,11,0.16), 0 0 18px rgba(245,158,11,0.42);
        }
        .status-dot.is-error {
            background: #ef4444;
            box-shadow: 0 0 0 5px rgba(239,68,68,0.14), 0 0 18px rgba(239,68,68,0.42);
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.45; transform: scale(0.86); }
        }
        .pulse { animation: pulse-dot 2s ease-in-out infinite; }

        .empty-graphic {
            width: 88px;
            height: 88px;
            border-radius: 32px;
            background:
                radial-gradient(circle at 28% 24%, white 0 12px, transparent 13px),
                linear-gradient(145deg, #111, #444);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 24px 42px rgba(0,0,0,0.18);
        }
    </style>
</head>
<body>
<div class="noise"></div>
<div class="shell space-y-6">

    <header class="topbar glass liquid flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <div class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M5 12.5h14" stroke-linecap="round"/>
                    <path d="M7.5 7.5h9v9h-9z"/>
                    <path d="M9 3.5v4M15 3.5v4M9 16.5v4M15 16.5v4M3.5 9h4M3.5 15h4M16.5 9h4M16.5 15h4" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] uppercase tracking-[0.28em] font-black text-black/45">System Observatory</div>
                <div class="text-lg font-black tracking-[-0.03em] truncate"><?= h(get_setting('site_name', 'AI 监控面板')) ?></div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 md:justify-end">
            <div class="pill px-4 py-2 text-xs font-bold">
                <span class="uppercase tracking-[0.18em] text-black/45">Auto Refresh</span>
                <span class="refresh-countdown text-black" id="countdown">--</span>
                <span class="text-black/45">s</span>
            </div>
            <button onclick="manualRefresh()" class="pill px-4 py-2 text-xs font-black hover:bg-black hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.5 15.5A7.5 7.5 0 0118.2 8M18.5 8.5A7.5 7.5 0 015.8 16"/>
                </svg>
                Refresh
            </button>
            <div id="statusBadge" class="pill px-4 py-2 text-xs font-black">
                <span class="status-dot pulse"></span>
                <span>ONLINE</span>
            </div>
        </div>
    </header>

    <section class="hero glass liquid grid lg:grid-cols-[1.18fr_0.82fr] gap-8 items-end">
        <div>
            <div class="kicker mb-5"><?= h($heroKicker) ?></div>
            <h1 class="hero-title mb-6"><span><?= h($heroTitleFirst) ?></span><?php if ($heroTitleSecond !== ''): ?><br><span><?= h($heroTitleSecond) ?></span><?php endif; ?></h1>
            <p class="max-w-2xl text-base md:text-lg text-black/58 font-semibold leading-relaxed">
                <?= h($heroDescription) ?>
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <?php foreach ($heroTags as $tag): ?>
                <div class="pill px-5 py-3 text-xs font-black uppercase tracking-[0.18em]"><?= h($tag) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3 gap-3">
            <div class="stat-card">
                <div class="text-[10px] uppercase tracking-[0.22em] font-black text-black/42">Total Channels</div>
                <div class="stat-number stat-total text-4xl font-black mt-1" id="statTotal">--</div>
            </div>
            <div class="stat-card">
                <div class="text-[10px] uppercase tracking-[0.22em] font-black text-black/42">Healthy</div>
                <div class="stat-number stat-healthy text-4xl font-black mt-1" id="statOnline">--</div>
            </div>
            <div class="stat-card">
                <div class="text-[10px] uppercase tracking-[0.22em] font-black text-black/42">Attention</div>
                <div class="stat-number stat-attention text-4xl font-black mt-1" id="statOffline">--</div>
            </div>
            <div class="stat-card sm:col-span-3 lg:col-span-1 xl:col-span-3">
                <div class="text-[10px] uppercase tracking-[0.22em] font-black text-black/42">Last Sync</div>
                <div class="tabular text-base font-black mt-1" id="lastSync">Waiting for data</div>
            </div>
        </div>
    </section>

    <?php if ($homeContent): ?>
    <section class="home-content glass liquid p-7">
        <?= $homeContent ?>
    </section>
    <?php endif; ?>

    <?php if (empty($groups)): ?>
    <section class="glass liquid rounded-[42px] p-12 text-center">
        <div class="empty-graphic mx-auto mb-6"></div>
        <div class="kicker mb-3">No Services</div>
        <h2 class="text-3xl font-black tracking-[-0.05em] mb-3">暂无监控数据</h2>
        <p class="text-black/52 font-semibold mb-6">请先在后台添加分组和渠道。</p>
        <a href="<?= h(site_url('admin/index.php')) ?>" class="pill px-6 py-3 text-sm font-black hover:bg-black hover:text-white transition">Open Admin</a>
    </section>
    <?php else: ?>
    <main class="space-y-6">
        <?php foreach ($groups as $group): ?>
        <?php $channels = get_channels_by_group((int)$group['id']); ?>
        <section class="group-card glass liquid mobile-collapsed" data-group-id="<?= (int)$group['id'] ?>">
            <div class="group-header flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center justify-between gap-4 min-w-0 w-full md:w-auto">
                    <div class="flex items-center gap-4 min-w-0">
                        <?php if (!empty($group['icon_url'])): ?>
                        <img src="<?= h($group['icon_url']) ?>" class="w-14 h-14 rounded-full object-cover border border-white/80 shadow-lg flex-shrink-0" alt="">
                        <?php else: ?>
                        <div class="service-mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 16.5c4-7 12-7 16 0" stroke-linecap="round"/>
                                <path d="M7 12c3-4.8 7-4.8 10 0" stroke-linecap="round"/>
                                <path d="M10 7.5h4" stroke-linecap="round"/>
                                <path d="M12 18h.01" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <?php endif; ?>
                        <div class="min-w-0">
                            <div class="text-[10px] uppercase tracking-[0.24em] font-black text-black/42">Provider Group</div>
                            <h2 class="text-2xl font-black tracking-[-0.05em] truncate"><?= h($group['name']) ?></h2>
                            <?php if ($group['description']): ?>
                            <p class="mt-1 text-sm text-black/52 font-semibold truncate"><?= h($group['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="group-toggle" aria-expanded="false" aria-label="展开或折叠分组" onclick="toggleGroup(this)">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="flex flex-col md:items-end gap-3 min-w-0">
                    <div class="pill px-5 py-3 text-xs font-black uppercase tracking-[0.16em] w-fit">
                        <?= (int)$group['channel_counts']['active'] ?>/<?= (int)$group['channel_counts']['total'] ?> Active
                    </div>
                    <div class="mobile-channel-dots" aria-label="渠道状态概览">
                        <?php foreach ($channels as $dotChannel):
                            $dotHasCheck = (bool)$dotChannel['last_check_at'];
                            $dotOk = $dotHasCheck && (int)$dotChannel['last_status'] === 200;
                            $dotLat = $dotChannel['last_latency'];
                            if (!$dotHasCheck)      { $dotClass = 's-unknown'; }
                            elseif (!$dotOk)        { $dotClass = 's-error'; }
                            elseif ($dotLat < 5000) { $dotClass = 's-normal'; }
                            elseif ($dotLat < 20000){ $dotClass = 's-slow'; }
                            else                    { $dotClass = 's-error'; }
                        ?>
                        <span class="mini-status-dot <?= $dotClass ?>" id="mini-dot-<?= (int)$dotChannel['id'] ?>" title="<?= h($dotChannel['name']) ?>"></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="group-body grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4 md:p-5">
                <?php foreach ($channels as $channel):
                    $hasCheck = (bool)$channel['last_check_at'];
                    $isOk     = $hasCheck && (int)$channel['last_status'] === 200;
                    $lat      = $channel['last_latency'];
                    $ping     = $channel['last_ping_latency'] ?? null;
                    $provider = trim($group['provider_name'] ?? '') ?: 'Unknown Provider';
                    $providerInitial = function_exists('mb_substr') ? mb_substr($provider, 0, 1, 'UTF-8') : substr($provider, 0, 1);

                    if (!$hasCheck)         { $badgeClass = 'bg-white/70 text-black/45 border-black/10'; $badgeText = 'Pending'; }
                    elseif (!$isOk)         { $badgeClass = 'bg-red-50 text-red-700 border-red-200';       $badgeText = 'Issue'; }
                    elseif ($lat < 5000)    { $badgeClass = 'bg-green-50 text-green-700 border-green-200'; $badgeText = 'Stable'; }
                    elseif ($lat < 20000)   { $badgeClass = 'bg-yellow-50 text-yellow-700 border-yellow-200'; $badgeText = 'Slow'; }
                    else                    { $badgeClass = 'bg-red-50 text-red-700 border-red-200';       $badgeText = 'Timeout'; }
                ?>
                <article class="channel-card min-w-0 overflow-hidden p-5" tabindex="0" data-channel-id="<?= $channel['id'] ?>">
                    <div class="flex items-start justify-between gap-3 mb-5">
                        <div class="flex items-center gap-3 min-w-0">
                            <?php if (!empty($group['icon_url'])): ?>
                            <img src="<?= h($group['icon_url']) ?>" class="w-14 h-14 rounded-full object-cover border border-white shadow-lg flex-shrink-0" alt="">
                            <?php else: ?>
                            <div class="service-mark text-lg font-black"><?= h($providerInitial) ?></div>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <div class="pill px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.14em] max-w-full">
                                    <span class="truncate"><?= h($provider) ?></span>
                                </div>
                                <h3 class="mt-2 text-lg font-black tracking-[-0.04em] break-words leading-tight"><?= h($channel['name']) ?></h3>
                            </div>
                        </div>
                        <span class="text-[10px] px-3 py-1.5 rounded-full font-black border flex-shrink-0 uppercase tracking-[0.12em] <?= $badgeClass ?>" id="badge-<?= $channel['id'] ?>"><?= $badgeText ?></span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="metric-card">
                            <div class="metric-label mb-2">Latency</div>
                            <div class="metric-value text-2xl font-black tracking-[-0.06em] <?= $lat === null ? 'value-muted' : ((int)$lat < 5000 ? 'value-good' : ((int)$lat < 20000 ? 'value-warn' : 'value-bad')) ?>" id="latency-<?= $channel['id'] ?>"><?= format_latency($channel['last_latency']) ?></div>
                            <div class="mt-1 text-xs font-bold text-black/40">Chat Response</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-label mb-2">Endpoint</div>
                            <div class="metric-value text-2xl font-black tracking-[-0.06em] <?= $ping === null ? 'value-muted' : ((int)$ping < 1000 ? 'value-good' : ((int)$ping < 3000 ? 'value-warn' : 'value-bad')) ?>" id="ping-<?= $channel['id'] ?>"><?= format_latency($ping !== null ? (int)$ping : null) ?></div>
                            <div class="mt-1 text-xs font-bold text-black/40">PING Check</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="metric-card py-3">
                            <div class="metric-label mb-2">Availability</div>
                            <div class="text-xl font-black tabular value-muted" id="uptime-<?= $channel['id'] ?>">--</div>
                        </div>
                        <div class="metric-card py-3">
                            <div class="metric-label mb-2">Last Check</div>
                            <div class="text-sm font-black tabular truncate" id="lastcheck-<?= $channel['id'] ?>"><?= $channel['last_check_at'] ? h($channel['last_check_at']) : 'Never' ?></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] uppercase tracking-[0.18em] font-black text-black/42">Recent Trace</span>
                        <span class="text-[10px] uppercase tracking-[0.18em] font-black text-black/42">Last 48</span>
                    </div>
                    <div class="status-strip" id="strip-<?= $channel['id'] ?>"></div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </main>
    <?php endif; ?>

    <footer class="text-center py-8 text-[11px] uppercase tracking-[0.22em] font-black text-black/35">
        AI Service Observatory · <?= date('Y') ?>
    </footer>
</div>

<script>
const checkInterval = <?= $checkInterval * 1000 ?>;
let countdown = Math.floor(checkInterval / 1000);
let isChecking = false;

function getMiniStatusClass(hasCheck, isOk, ms) {
    if (!hasCheck) return 's-unknown';
    if (!isOk) return 's-error';
    if (ms === null || ms === undefined || Number.isNaN(Number(ms))) return 's-error';
    const n = Number(ms);
    if (n < 5000) return 's-normal';
    if (n < 20000) return 's-slow';
    return 's-error';
}

function updateMiniStatusDot(channelId, className) {
    const el = document.getElementById(`mini-dot-${channelId}`);
    if (!el) return;
    el.className = `mini-status-dot ${className}`;
}

function toggleGroup(button) {
    const card = button.closest('.group-card');
    if (!card) return;
    const collapsed = card.classList.toggle('mobile-collapsed');
    button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
}

function applyInitialGroupCollapse() {
    document.querySelectorAll('.group-card').forEach(card => {
        const button = card.querySelector('.group-toggle');
        if (button && button.getAttribute('aria-expanded') !== 'true') {
            card.classList.add('mobile-collapsed');
        }
    });
}

function getLogStatus(log) {
    return Number(log.status_code ?? log.status ?? 0);
}

function getLogLatency(log) {
    return log.latency === null || log.latency === undefined ? null : Number(log.latency);
}

function getBlockClass(log) {
    if (!log) return 's-unknown';
    const status = getLogStatus(log);
    if (status !== 200) return 's-error';
    const ms = getLogLatency(log);
    if (ms === null) return 's-error';
    if (ms < 5000) return 's-normal';
    if (ms < 20000) return 's-slow';
    return 's-error';
}

function fmtTime(iso) {
    if (!iso) return 'Never';
    const d = new Date(String(iso).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return iso;
    return d.getFullYear() + '/' +
        String(d.getMonth()+1).padStart(2,'0') + '/' +
        String(d.getDate()).padStart(2,'0') + ' ' +
        String(d.getHours()).padStart(2,'0') + ':' +
        String(d.getMinutes()).padStart(2,'0');
}

function fmtLatency(ms) {
    if (ms === null || ms === undefined || Number.isNaN(Number(ms))) return '--';
    const n = Number(ms);
    return n < 5000 ? n + ' ms' : (n / 1000).toFixed(1) + ' s';
}

function getValueClassByLatency(ms, goodLimit, warnLimit) {
    if (ms === null || ms === undefined || Number.isNaN(Number(ms))) return 'value-muted';
    const n = Number(ms);
    if (n < goodLimit) return 'value-good';
    if (n < warnLimit) return 'value-warn';
    return 'value-bad';
}

function getUptimeClass(value) {
    if (!value || value === '--') return 'value-muted';
    const n = Number(String(value).replace('%', ''));
    if (Number.isNaN(n)) return 'value-muted';
    if (n >= 85) return 'value-good';
    if (n >= 60) return 'value-warn';
    return 'value-bad';
}

function calcUptime(logs) {
    if (!logs || !logs.length) return '--';
    const ok = logs.filter(l => getLogStatus(l) === 200 && getLogLatency(l) !== null && getLogLatency(l) < 20000).length;
    return (ok / logs.length * 100).toFixed(1) + '%';
}

function createStatusBlock(log) {
    const block = document.createElement('div');
    block.className = `status-block ${getBlockClass(log)}`;
    block.tabIndex = 0;

    const tip = document.createElement('div');
    tip.className = 's-tip';

    if (!log) {
        tip.textContent = 'No data yet';
        block.appendChild(tip);
        return block;
    }

    const lat = getLogLatency(log);
    const ping = log.ping_latency === null || log.ping_latency === undefined ? null : Number(log.ping_latency);
    const cls = getBlockClass(log);
    const statusLabel = cls === 's-normal' ? 'Stable' : cls === 's-slow' ? 'Slow' : cls === 's-error' ? 'Issue' : 'Unknown';
    tip.textContent = fmtTime(log.checked_at) + ' · ' + statusLabel + ' · ' + fmtLatency(lat) + (ping !== null ? ' · PING ' + fmtLatency(ping) : '');
    block.appendChild(tip);
    return block;
}

function renderStrip(channelId, logs) {
    const el = document.getElementById(`strip-${channelId}`);
    if (!el) return;
    if (!Array.isArray(logs)) return;

    el.innerHTML = '';
    const visibleLogs = logs.slice(-48);
    const missing = Math.max(0, 48 - visibleLogs.length);

    for (let i = 0; i < missing; i++) {
        el.appendChild(createStatusBlock(null));
    }
    visibleLogs.forEach(log => {
        el.appendChild(createStatusBlock(log));
    });
}

function updateAll(data) {
    const channels = data.channels || [];
    let total = channels.length, online = 0, offline = 0;
    let latestTime = '';

    channels.forEach(ch => {
        const hasCheck = !!ch.last_check_at;
        const isOk = hasCheck && Number(ch.last_status) === 200;
        const ms = ch.last_latency === null || ch.last_latency === undefined ? null : Number(ch.last_latency);
        const ping = ch.last_ping_latency === null || ch.last_ping_latency === undefined ? null : Number(ch.last_ping_latency);
        updateMiniStatusDot(ch.id, getMiniStatusClass(hasCheck, isOk, ms));

        if (hasCheck) {
            isOk ? online++ : offline++;
            if (!latestTime || String(ch.last_check_at) > latestTime) latestTime = String(ch.last_check_at);
        }

        const latEl = document.getElementById(`latency-${ch.id}`);
        if (latEl) {
            latEl.textContent = fmtLatency(ms);
            latEl.className = 'metric-value text-2xl font-black tracking-[-0.06em] ' + getValueClassByLatency(ms, 5000, 20000);
        }

        const pingEl = document.getElementById(`ping-${ch.id}`);
        if (pingEl) {
            pingEl.textContent = fmtLatency(ping);
            pingEl.className = 'metric-value text-2xl font-black tracking-[-0.06em] ' + getValueClassByLatency(ping, 1000, 3000);
        }

        const badge = document.getElementById(`badge-${ch.id}`);
        if (badge) {
            let cls, text;
            if (!hasCheck)       { cls = 'bg-white/70 text-black/45 border-black/10'; text = 'Pending'; }
            else if (!isOk)      { cls = 'bg-red-50 text-red-700 border-red-200';       text = 'Issue'; }
            else if (ms < 5000)  { cls = 'bg-green-50 text-green-700 border-green-200'; text = 'Stable'; }
            else if (ms < 20000) { cls = 'bg-yellow-50 text-yellow-700 border-yellow-200'; text = 'Slow'; }
            else                 { cls = 'bg-red-50 text-red-700 border-red-200';       text = 'Timeout'; }
            badge.className = `text-[10px] px-3 py-1.5 rounded-full font-black border flex-shrink-0 uppercase tracking-[0.12em] ${cls}`;
            badge.textContent = text;
        }

        const uptimeEl = document.getElementById(`uptime-${ch.id}`);
        if (uptimeEl) {
            const uptime = calcUptime(ch.logs);
            uptimeEl.textContent = uptime;
            uptimeEl.className = 'text-xl font-black tabular ' + getUptimeClass(uptime);
        }

        const lastCheckEl = document.getElementById(`lastcheck-${ch.id}`);
        if (lastCheckEl) lastCheckEl.textContent = hasCheck ? fmtTime(ch.last_check_at) : 'Never';

        renderStrip(ch.id, ch.logs);
    });

    document.getElementById('statTotal').textContent = total;
    document.getElementById('statOnline').textContent = online;
    document.getElementById('statOffline').textContent = offline;
    const syncEl = document.getElementById('lastSync');
    if (syncEl) syncEl.textContent = latestTime ? fmtTime(latestTime) : 'Waiting for data';
}

function setGlobalBadge(state) {
    const el = document.getElementById('statusBadge');
    const cfg = {
        ok: ['pulse', 'ONLINE', ''],
        checking: ['', 'CHECKING', 'is-checking'],
        error: ['', 'OFFLINE', 'is-error'],
    }[state] || ['pulse', 'ONLINE', ''];

    el.innerHTML = `<span class="status-dot ${cfg[0]} ${cfg[2]}"></span><span>${cfg[1]}</span>`;
}

async function checkAndLoad() {
    if (isChecking) return;
    isChecking = true;
    try {
        const res = await fetch(<?= json_encode(site_url('api/status.php')) ?>);
        const data = await res.json();
        updateAll(data);
        setGlobalBadge('ok');
    } catch(e) {
        console.error('加载数据失败:', e);
        setGlobalBadge('error');
    }
    isChecking = false;
}

function manualRefresh() {
    countdown = Math.floor(checkInterval / 1000);
    document.getElementById('countdown').textContent = countdown;
    checkAndLoad();
}

document.addEventListener('DOMContentLoaded', async () => {
    applyInitialGroupCollapse();
    try {
        const res = await fetch(<?= json_encode(site_url('api/status.php')) ?>);
        const data = await res.json();
        updateAll(data);
    } catch(e) {
        console.error('加载数据失败:', e);
    }
    startCountdown();
});

function startCountdown() {
    const el = document.getElementById('countdown');
    el.textContent = countdown;
    setInterval(() => {
        countdown--;
        el.textContent = countdown;
        if (countdown <= 0) {
            countdown = Math.floor(checkInterval / 1000);
            el.textContent = countdown;
            checkAndLoad();
        }
    }, 1000);
}
</script>
</body>
</html>