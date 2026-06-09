<?php
declare(strict_types=1);

$tabs = [
    'overview' => 'ОБЗОР',
    'benchmarks' => 'БЕНЧМАРКИ',
    'stats' => 'ПОКАЗАТЕЛИ',
    'laning' => 'ЛАЙНИНГ',
    'damage' => 'УРОН',
    'gold' => 'ЗАРАБОТОК',
    'items' => 'ПРЕДМЕТЫ',
    'graphs' => 'ГРАФИКИ',
    'compare' => 'СРАВНЕНИЕ',
    'abilities' => 'СПОСОБНОСТИ',
    'objectives' => 'ЦЕЛИ',
    'vision' => 'ВИЖЕН',
    'actions' => 'ДЕЙСТВИЯ',
    'teamfights' => 'КОМАНДНЫЕ БОИ',
    'fantasy' => 'ФЭНТЕЗИ',
    'chat' => 'ЧАТ',
    'story' => 'ИСТОРИЯ',
    'log' => 'ЖУРНАЛ',
];

$current_tab = $current_tab ?? 'overview';
$page_name = $page_title ?? ($tabs[$current_tab] ?? 'Dota 2');
$is_match_page = isset($match_id, $match);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_match_page ? 'Match ' . e($match_id) . ' - ' . e($page_name) : e($page_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: { preflight: false },
            theme: {
                extend: {
                    colors: {
                        base: '#0f1417',
                        surface: '#1c242d',
                        panel: '#171f25',
                        'row-hover': '#202a32',
                        line: '#252f3a',
                        main: '#d6d9dc',
                        muted: '#8a96a3',
                        radiant: '#2ecc71',
                        dire: '#e74c3c',
                        gold: '#f1c40f',
                        link: '#3498db',
                        'link-hover': '#5dade2',
                    },
                    boxShadow: {
                        card: '0 4px 15px rgba(0, 0, 0, 0.5)',
                        soft: '0 1px 3px rgba(0, 0, 0, 0.4)',
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="<?php echo e(asset_url('css/root.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/base.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/overview.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/benchmarks.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/damage.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/vision.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/laning.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/gold.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/abilities.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/items.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/graphs.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/compare.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/teamfights.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/home.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/profile.css')); ?>">
    <script>window.OD_BASE = <?php echo json_encode(rtrim(app_url(), '/') . '/', JSON_UNESCAPED_SLASHES); ?>;</script>
    <script defer src="<?php echo e(asset_url('src/assets/app.js')); ?>"></script>
</head>
<body class="bg-base text-main">
<div class="mx-auto max-w-[1200px] rounded-lg bg-surface p-5 shadow-card">
    <header class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <a class="text-lg font-bold tracking-wide text-main no-underline transition-colors hover:text-link" href="<?php echo e(app_url()); ?>">OpenDoter</a>
        <div class="flex w-full flex-col gap-2 sm:max-w-[640px] sm:flex-row sm:items-center">
            <form class="flex w-full gap-2" action="<?php echo e(app_url('search')); ?>" method="get">
                <input type="search" name="q" value="<?php echo e($search_query ?? ''); ?>" placeholder="Найти игрока или Match ID"
                       class="min-w-0 flex-1 rounded border border-line bg-panel px-3 py-2 text-main placeholder:text-muted focus:border-link focus:outline-none focus:ring-2 focus:ring-link/60">
                <button type="submit"
                        class="rounded border border-link bg-link/10 px-4 py-2 text-xs font-bold uppercase text-link transition-colors hover:bg-link/20 hover:text-link-hover">Найти</button>
            </form>
            <?php $me_id = current_account_id(); ?>
            <div class="shrink-0">
                <?php if ($me_id !== null): ?>
                    <div class="flex items-center gap-2">
                        <a href="<?php echo e(player_url($me_id)); ?>" class="account-chip" data-account-id="<?php echo e($me_id); ?>">
                            <span class="account-chip-avatar" data-od-avatar>Игрок</span>
                            <span class="account-chip-name" data-od-name>Мой профиль</span>
                        </a>
                        <a href="<?php echo e(app_url('logout')); ?>" class="account-logout" title="Выйти">Выход</a>
                    </div>
                <?php else: ?>
                    <button type="button" data-od-login-open
                            class="whitespace-nowrap rounded border border-radiant/60 bg-radiant/10 px-4 py-2 text-xs font-bold uppercase text-radiant transition-colors hover:bg-radiant/20">Войти</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div id="od-login-modal" class="od-modal" hidden>
        <div class="od-modal-backdrop" data-od-login-close></div>
        <div class="od-modal-card">
            <div class="od-modal-head">
                <span class="text-base font-bold uppercase tracking-wide text-main">Вход в профиль</span>
                <button type="button" data-od-login-close class="od-modal-x" aria-label="Закрыть">×</button>
            </div>
            <p class="mb-3 text-[13px] leading-relaxed text-muted">Введите свой Dota account ID, Steam ID64 или ссылку на Steam-профиль. Мы запомним вас на этом устройстве.</p>
            <form action="<?php echo e(app_url('login')); ?>" method="get" class="flex flex-col gap-3">
                <input type="text" name="account" required autofocus placeholder="напр. 321580662 или https://steamcommunity.com/profiles/..."
                       class="w-full rounded border border-line bg-panel px-3 py-2.5 text-main placeholder:text-muted focus:border-link focus:outline-none focus:ring-2 focus:ring-link/60">
                <button type="submit" class="rounded border border-radiant/60 bg-radiant/10 px-4 py-2.5 text-xs font-bold uppercase text-radiant transition-colors hover:bg-radiant/20">Войти</button>
            </form>
            <p class="mt-3 text-[11px] text-muted">Совет: account ID виден в адресе вашего профиля на OpenDota.</p>
        </div>
    </div>

    <?php if ($is_match_page): ?>
    <div class="mb-4 flex flex-col gap-4 rounded-lg border border-line bg-panel px-5 py-4 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-col gap-1">
            <span class="text-[15px] font-bold text-main">Матч ID: <?php echo e($match_id); ?></span>
            <span class="text-[11px] text-muted">Завершен: <?php echo e($match_end_time); ?></span>
        </div>
        <div class="flex items-center gap-4 rounded-full border border-line bg-black/30 px-5 py-2">
            <span class="text-sm font-bold uppercase text-radiant">Свет</span>
            <span class="font-mono text-xl font-bold"><?php echo e($radiant_score); ?></span>
            <span class="text-lg text-muted">:</span>
            <span class="font-mono text-xl font-bold"><?php echo e($dire_score); ?></span>
            <span class="text-sm font-bold uppercase text-dire">Тьма</span>
        </div>
        <div class="flex flex-col gap-1 md:items-end">
            <span class="font-mono text-base font-bold text-main">Время: <?php echo e($match_duration); ?></span>
            <span class="text-[11px] font-bold uppercase <?php echo !empty($match['radiant_win']) ? 'text-radiant' : 'text-dire'; ?>">
                Победа сил <?php echo !empty($match['radiant_win']) ? 'света' : 'Тьмы'; ?>
            </span>
        </div>
    </div>

    <script type="application/json" id="od-track-match">
    <?php echo json_encode([
        'type' => 'match',
        'match_id' => (string) $match_id,
        'radiant_win' => !empty($match['radiant_win']),
        'radiant_score' => $radiant_score ?? null,
        'dire_score' => $dire_score ?? null,
        'duration' => $match_duration ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>
    </script>

    <nav class="mb-6 flex flex-wrap gap-1 border-b border-line pb-1.5" aria-label="Навигация по матчу">
        <?php foreach ($tabs as $tab => $name): ?>
            <a href="<?php echo e(match_url((string) $match_id, $tab)); ?>"
               class="whitespace-nowrap border-b-2 px-3.5 py-2.5 text-[11px] font-bold uppercase tracking-wide transition-colors <?php echo ($current_tab === $tab) ? 'border-link bg-link/5 text-link' : 'border-transparent text-muted hover:border-muted hover:text-main'; ?>">
                <?php echo e($name); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
