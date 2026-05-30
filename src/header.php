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
    'abilities' => 'СПОСОБНОСТИ',
    'objectives' => 'ЦЕЛИ',
    'vision' => 'ВИЖЕН',
    'actions' => 'ДЕЙСТВИЯ',
    'teamfights' => 'КОМАНДНЫЕ БОИ',
    'fantasy' => 'ФЭНТЕЗИ',
    'chat' => 'ЧАТ',
    'story' => 'ИСТОРИЯ',
    'log' => 'ЖУРНАЛ',
    'cosmetics' => 'СНАРЯЖЕНИЕ',
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
    <link rel="stylesheet" href="<?php echo e(asset_url('css/root.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/base.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/header.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/overview.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/vision.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/laning.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/match.css')); ?>">
</head>
<body>
<div class="container">
    <header class="site-topbar">
        <a class="brand-link" href="<?php echo e(app_url()); ?>">OpenDoter</a>
        <form class="global-search" action="<?php echo e(app_url('search')); ?>" method="get">
            <input type="search" name="q" value="<?php echo e($search_query ?? ''); ?>" placeholder="Найти игрока или Match ID">
            <button type="submit">Найти</button>
        </form>
    </header>

    <?php if ($is_match_page): ?>
    <div class="match-info-banner">
        <div class="match-meta">
            <span class="meta-label">Матч ID: <?php echo e($match_id); ?></span>
            <span class="meta-date">Завершен: <?php echo e($match_end_time); ?></span>
        </div>
        <div class="match-score-container">
            <span class="score-team radiant-title">Свет</span>
            <span class="score-kills"><?php echo e($radiant_score); ?></span>
            <span class="score-divider">:</span>
            <span class="score-kills"><?php echo e($dire_score); ?></span>
            <span class="score-team dire-title">Тьма</span>
        </div>
        <div class="match-meta-right">
            <span class="match-duration">Время: <?php echo e($match_duration); ?></span>
            <span class="match-victory <?php echo !empty($match['radiant_win']) ? 'radiant-title' : 'dire-title'; ?>">
                Победа сил <?php echo !empty($match['radiant_win']) ? 'света' : 'Тьмы'; ?>
            </span>
        </div>
    </div>

    <nav class="tabs-nav" aria-label="Навигация по матчу">
        <?php foreach ($tabs as $tab => $name): ?>
            <a href="<?php echo e(match_url((string) $match_id, $tab)); ?>" class="tab-link <?php echo ($current_tab === $tab) ? 'active' : ''; ?>">
                <?php echo e($name); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
