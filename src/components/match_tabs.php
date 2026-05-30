<?php
declare(strict_types=1);

/*
 * Общие компоненты для вкладок страницы матча (показатели, урон, заработок и т.д.).
 * Рендер-функции вызываются из src/views/<tab>.php.
 */

function mt_format_time(int $seconds): string
{
    $neg = $seconds < 0;
    $s = abs($seconds);
    return ($neg ? '-' : '') . sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

function mt_sum_assoc($values): int
{
    if (!is_array($values)) {
        return 0;
    }
    $sum = 0;
    foreach ($values as $v) {
        if (is_numeric($v)) {
            $sum += (int) $v;
        }
    }
    return $sum;
}

function mt_empty_state(string $text): void
{
    echo '<div class="empty-state"><span>' . e($text) . '</span></div>';
}

function mt_team_header(string $title, string $team_class, bool $is_winner, string $subtitle = ''): void
{
    echo '<div class="team-header"><div>';
    echo '<span class="team-title ' . e($team_class) . '">' . e($title) . '</span>';
    if ($subtitle !== '') {
        echo '<span class="team-subtitle"> - ' . e($subtitle) . '</span>';
    }
    echo '</div>';
    if ($is_winner) {
        echo '<span class="winner-label">Победитель</span>';
    }
    echo '</div>';
}

function mt_render_player_cell(array $player, array $heroes): void
{
    $name = (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним');
    $hero_id = (int) ($player['hero_id'] ?? 0);
    $img = get_hero_img($hero_id, $heroes);
    echo '<div class="player-cell">';
    if ($img) {
        echo '<img class="hero-img" src="' . e($img) . '" alt="">';
    }
    echo '<div class="player-info">';
    if (!empty($player['account_id'])) {
        echo '<a class="player-name" href="' . e(player_url($player['account_id'])) . '">' . e($name) . '</a>';
    } else {
        echo '<span class="player-name">' . e($name) . '</span>';
    }
    echo '<span class="player-rank">' . e(get_hero_name($hero_id, $heroes)) . '</span>';
    echo '</div></div>';
}

/**
 * @param array<int,array{label:string,title?:string,value:callable,total?:bool}> $columns
 */
function mt_stat_table(string $title, string $team_class, bool $is_winner, string $subtitle, array $players, array $heroes, array $columns): void
{
    mt_team_header($title, $team_class, $is_winner, $subtitle);
    echo '<table class="overview-table"><thead><tr><th class="player-column">Игрок</th>';
    foreach ($columns as $c) {
        echo '<th class="col-center" title="' . e((string) ($c['title'] ?? $c['label'])) . '">' . e($c['label']) . '</th>';
    }
    echo '</tr></thead><tbody>';

    $count = count($columns);
    $totals = array_fill(0, $count, 0.0);
    $sum_ok = array_fill(0, $count, true);

    foreach ($players as $player) {
        echo '<tr><td>';
        mt_render_player_cell($player, $heroes);
        echo '</td>';
        foreach ($columns as $idx => $c) {
            $value = ($c['value'])($player);
            echo '<td class="col-center">' . e((string) $value) . '</td>';
            if (is_numeric($value)) {
                $totals[$idx] += (float) $value;
            } else {
                $sum_ok[$idx] = false;
            }
        }
        echo '</tr>';
    }

    echo '<tr class="totals-row"><td>Всего</td>';
    foreach ($columns as $idx => $c) {
        $show = ($c['total'] ?? true) && $sum_ok[$idx];
        if (!$show) {
            echo '<td class="col-center">-</td>';
            continue;
        }
        $total = $totals[$idx];
        $display = (fmod($total, 1.0) !== 0.0) ? (string) round($total, 1) : (string) (int) $total;
        echo '<td class="col-center">' . e($display) . '</td>';
    }
    echo '</tr></tbody></table>';
}

function mt_two_team_stats(array $match, array $radiant, array $dire, array $heroes, string $subtitle, array $columns): void
{
    $radiant_win = !empty($match['radiant_win']);
    mt_stat_table('Свет', 'radiant-title', $radiant_win, $subtitle, $radiant, $heroes, $columns);
    mt_stat_table('Тьма', 'dire-title', !$radiant_win, $subtitle, $dire, $heroes, $columns);
}

function mt_slot_name_map(array $match): array
{
    $map = [];
    foreach (($match['players'] ?? []) as $player) {
        if (!is_array($player)) {
            continue;
        }
        $slot = $player['player_slot'] ?? null;
        if ($slot === null) {
            continue;
        }
        $map[(int) $slot] = (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним');
    }
    return $map;
}

/* ------------------------------------------------------------------ */
/* ПОКАЗАТЕЛИ / УРОН / ЗАРАБОТОК / ФЭНТЕЗИ / ДЕЙСТВИЯ      */
/* ------------------------------------------------------------------ */

function render_stats_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $columns = [
        ['label' => 'Ур', 'title' => 'Уровень', 'value' => fn($p) => (int) ($p['level'] ?? 0)],
        ['label' => 'У', 'title' => 'Убийства', 'value' => fn($p) => (int) ($p['kills'] ?? 0)],
        ['label' => 'С', 'title' => 'Смерти', 'value' => fn($p) => (int) ($p['deaths'] ?? 0)],
        ['label' => 'П', 'title' => 'Помощь', 'value' => fn($p) => (int) ($p['assists'] ?? 0)],
        ['label' => 'ДК', 'title' => 'Добито крипов', 'value' => fn($p) => (int) ($p['last_hits'] ?? 0)],
        ['label' => 'НО', 'title' => 'Денаи', 'value' => fn($p) => (int) ($p['denies'] ?? 0)],
        ['label' => 'З/М', 'title' => 'Золото в минуту', 'value' => fn($p) => (int) ($p['gold_per_min'] ?? 0)],
        ['label' => 'О/М', 'title' => 'Опыт в минуту', 'value' => fn($p) => (int) ($p['xp_per_min'] ?? 0)],
        ['label' => 'Ценность', 'title' => 'Общая ценность (Net Worth)', 'value' => fn($p) => (int) ($p['net_worth'] ?? $p['total_gold'] ?? 0)],
        ['label' => 'Урон', 'title' => 'Урон по героям', 'value' => fn($p) => (int) ($p['hero_damage'] ?? 0)],
        ['label' => 'Леч', 'title' => 'Лечение союзников', 'value' => fn($p) => (int) ($p['hero_healing'] ?? 0)],
        ['label' => 'Пстр', 'title' => 'Урон по постройкам', 'value' => fn($p) => (int) ($p['tower_damage'] ?? 0)],
    ];
    mt_two_team_stats($match, $radiant, $dire, $heroes, 'Показатели', $columns);
}

function render_damage_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $columns = [
        ['label' => 'Урон по героям', 'value' => fn($p) => (int) ($p['hero_damage'] ?? 0)],
        ['label' => 'Получено урона', 'title' => 'Урон, полученный героем', 'value' => fn($p) => mt_sum_assoc($p['damage_taken'] ?? [])],
        ['label' => 'Лечение', 'value' => fn($p) => (int) ($p['hero_healing'] ?? 0)],
        ['label' => 'Урон по постройкам', 'value' => fn($p) => (int) ($p['tower_damage'] ?? 0)],
        ['label' => 'У', 'title' => 'Убийства', 'value' => fn($p) => (int) ($p['kills'] ?? 0)],
    ];
    mt_two_team_stats($match, $radiant, $dire, $heroes, 'Урон', $columns);
}

function render_gold_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $columns = [
        ['label' => 'Ценность', 'title' => 'Общая ценность (Net Worth)', 'value' => fn($p) => (int) ($p['net_worth'] ?? $p['total_gold'] ?? 0)],
        ['label' => 'З/М', 'title' => 'Золото в минуту', 'value' => fn($p) => (int) ($p['gold_per_min'] ?? 0)],
        ['label' => 'Потрачено', 'title' => 'Золота потрачено', 'value' => fn($p) => (int) ($p['gold_spent'] ?? 0)],
        ['label' => 'В запасе', 'title' => 'Текущее золото', 'value' => fn($p) => (int) ($p['gold'] ?? 0)],
        ['label' => 'ДК', 'title' => 'Добито крипов', 'value' => fn($p) => (int) ($p['last_hits'] ?? 0)],
        ['label' => 'НО', 'title' => 'Денаи', 'value' => fn($p) => (int) ($p['denies'] ?? 0)],
    ];
    mt_two_team_stats($match, $radiant, $dire, $heroes, 'Заработок', $columns);
}

function mt_fantasy_points(array $p): float
{
    $kills = (int) ($p['kills'] ?? 0);
    $deaths = (int) ($p['deaths'] ?? 0);
    $lh = (int) ($p['last_hits'] ?? 0);
    $gpm = (int) ($p['gold_per_min'] ?? 0);
    $towers = (int) ($p['towers_killed'] ?? 0);
    $roshans = (int) ($p['roshans_killed'] ?? 0);
    $tf = (float) ($p['teamfight_participation'] ?? 0);
    $obs = (int) ($p['obs_placed'] ?? 0);
    $camps = (int) ($p['camps_stacked'] ?? 0);
    $runes = (int) ($p['rune_pickups'] ?? 0);
    $firstblood = !empty($p['firstblood_claimed']) ? 1 : 0;
    $stuns = (float) ($p['stuns'] ?? 0);

    return $kills * 0.3
        - $deaths * 0.3
        + $lh * 0.003
        + $gpm * 0.002
        + $towers * 1.0
        + $roshans * 1.0
        + $tf * 3.0
        + $obs * 0.5
        + $camps * 0.5
        + $runes * 0.25
        + $firstblood * 4.0
        + $stuns * 0.05;
}

function render_fantasy_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $columns = [
        ['label' => 'Очки', 'title' => 'Фэнтези-очки (приблизительно)', 'value' => fn($p) => round(mt_fantasy_points($p), 2)],
        ['label' => 'У', 'title' => 'Убийства', 'value' => fn($p) => (int) ($p['kills'] ?? 0)],
        ['label' => 'С', 'title' => 'Смерти', 'value' => fn($p) => (int) ($p['deaths'] ?? 0)],
        ['label' => 'ДК', 'title' => 'Добито крипов', 'value' => fn($p) => (int) ($p['last_hits'] ?? 0)],
        ['label' => 'Башни', 'title' => 'Разрушено башен', 'value' => fn($p) => (int) ($p['towers_killed'] ?? 0)],
        ['label' => 'Рошан', 'title' => 'Убийства Рошана', 'value' => fn($p) => (int) ($p['roshans_killed'] ?? 0)],
        ['label' => 'ТФ%', 'title' => 'Участие в сражениях', 'value' => fn($p) => (int) round(((float) ($p['teamfight_participation'] ?? 0)) * 100)],
        ['label' => 'Варды', 'title' => 'Поставлено обзорных вардов', 'value' => fn($p) => (int) ($p['obs_placed'] ?? 0)],
    ];
    mt_two_team_stats($match, $radiant, $dire, $heroes, 'Фэнтези', $columns);
}

function render_actions_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $has_data = false;
    foreach (array_merge($radiant, $dire) as $p) {
        if (!empty($p['actions_per_min']) || !empty($p['actions'])) {
            $has_data = true;
            break;
        }
    }
    if (!$has_data) {
        mt_empty_state('Данные о действиях (APM) доступны только для обработанных матчей.');
        return;
    }
    $columns = [
        ['label' => 'Действий/мин', 'title' => 'APM — действий в минуту', 'value' => fn($p) => (int) round((float) ($p['actions_per_min'] ?? 0))],
        ['label' => 'Всего действий', 'value' => fn($p) => mt_sum_assoc($p['actions'] ?? [])],
    ];
    mt_two_team_stats($match, $radiant, $dire, $heroes, 'Действия', $columns);
}

/* ------------------------------------------------------------------ */
/* БЕНЧМАРКИ                                                       */
/* ------------------------------------------------------------------ */

function render_benchmarks_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $metrics = [
        'gold_per_min' => 'Золото/мин',
        'xp_per_min' => 'Опыт/мин',
        'kills_per_min' => 'Убийства/мин',
        'last_hits_per_min' => 'Добивания/мин',
        'hero_damage_per_min' => 'Урон/мин',
        'hero_healing_per_min' => 'Лечение/мин',
        'tower_damage' => 'Урон по постройкам',
        'stuns_per_min' => 'Оглушения/мин',
    ];

    $has_data = false;
    foreach (array_merge($radiant, $dire) as $p) {
        if (!empty($p['benchmarks']) && is_array($p['benchmarks'])) {
            $has_data = true;
            break;
        }
    }
    if (!$has_data) {
        mt_empty_state('Бенчмарки недоступны для этого матча.');
        return;
    }

    $radiant_win = !empty($match['radiant_win']);
    mt_render_benchmarks_side('Свет', 'radiant-title', $radiant_win, $radiant, $heroes, $metrics);
    mt_render_benchmarks_side('Тьма', 'dire-title', !$radiant_win, $dire, $heroes, $metrics);
}

function mt_render_benchmarks_side(string $title, string $team_class, bool $is_winner, array $players, array $heroes, array $metrics): void
{
    mt_team_header($title, $team_class, $is_winner, 'Процентиль по герою');
    echo '<table class="overview-table"><thead><tr><th class="player-column">Игрок</th>';
    foreach ($metrics as $label) {
        echo '<th class="col-center">' . e($label) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($players as $player) {
        echo '<tr><td>';
        mt_render_player_cell($player, $heroes);
        echo '</td>';
        $benchmarks = is_array($player['benchmarks'] ?? null) ? $player['benchmarks'] : [];
        foreach ($metrics as $key => $label) {
            $entry = $benchmarks[$key] ?? null;
            if (!is_array($entry)) {
                echo '<td class="col-center">-</td>';
                continue;
            }
            $pct = (int) round(((float) ($entry['pct'] ?? 0)) * 100);
            $raw = (float) ($entry['raw'] ?? 0);
            $raw_display = $raw >= 100 ? (string) (int) round($raw) : (string) round($raw, 2);
            echo '<td class="col-center"><div class="mt-bench">';
            echo '<div class="mt-bench-bar"><span style="width:' . $pct . '%"></span></div>';
            echo '<div class="mt-bench-label">' . e($raw_display) . ' <span class="mt-bench-pct">' . $pct . '%</span></div>';
            echo '</div></td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
}

/* ------------------------------------------------------------------ */
/* ПРЕДМЕТЫ                                                         */
/* ------------------------------------------------------------------ */

function render_items_tab(array $match, array $radiant, array $dire, array $heroes, array $items_by_id): void
{
    $radiant_win = !empty($match['radiant_win']);
    mt_render_items_side('Свет', 'radiant-title', $radiant_win, $radiant, $heroes, $items_by_id);
    mt_render_items_side('Тьма', 'dire-title', !$radiant_win, $dire, $heroes, $items_by_id);
}

function mt_render_items_side(string $title, string $team_class, bool $is_winner, array $players, array $heroes, array $items_by_id): void
{
    mt_team_header($title, $team_class, $is_winner, 'Предметы');
    echo '<table class="overview-table"><thead><tr>';
    echo '<th class="player-column">Игрок</th><th>Предметы</th><th class="col-center">Рюкзак</th><th class="col-center">Нейтр.</th>';
    echo '</tr></thead><tbody>';
    foreach ($players as $player) {
        echo '<tr><td>';
        mt_render_player_cell($player, $heroes);
        echo '</td><td><div class="items-cell">';
        for ($i = 0; $i < 6; $i++) {
            mt_item_slot((int) ($player["item_{$i}"] ?? 0), $items_by_id, 'item-slot');
        }
        echo '</div></td><td class="col-center"><div class="items-cell">';
        for ($i = 0; $i < 3; $i++) {
            mt_item_slot((int) ($player["backpack_{$i}"] ?? 0), $items_by_id, 'item-slot');
        }
        echo '</div></td><td class="col-center"><div class="items-cell">';
        mt_item_slot((int) ($player['item_neutral'] ?? 0), $items_by_id, 'item-neutral');
        echo '</div></td></tr>';
    }
    echo '</tbody></table>';
}

function mt_item_slot(int $item_id, array $items_by_id, string $class): void
{
    $img = get_item_img($item_id, $items_by_id);
    $title = get_item_title($item_id, $items_by_id);
    echo '<div class="' . e($class) . '" title="' . e($title) . '">';
    if ($img) {
        echo '<img src="' . e($img) . '" alt="' . e($title) . '">';
    } elseif ($item_id > 0) {
        echo '<span class="missing-item">!</span>';
    }
    echo '</div>';
}

/* ------------------------------------------------------------------ */
/* СПОСОБНОСТИ                                                     */
/* ------------------------------------------------------------------ */

function render_abilities_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $has_data = false;
    foreach (array_merge($radiant, $dire) as $p) {
        if (!empty($p['ability_upgrades_arr']) && is_array($p['ability_upgrades_arr'])) {
            $has_data = true;
            break;
        }
    }
    if (!$has_data) {
        mt_empty_state('Порядок прокачки способностей доступен только для обработанных матчей.');
        return;
    }
    $radiant_win = !empty($match['radiant_win']);
    mt_render_abilities_side('Свет', 'radiant-title', $radiant_win, $radiant, $heroes);
    mt_render_abilities_side('Тьма', 'dire-title', !$radiant_win, $dire, $heroes);
}

function mt_render_abilities_side(string $title, string $team_class, bool $is_winner, array $players, array $heroes): void
{
    mt_team_header($title, $team_class, $is_winner, 'Порядок изучения способностей');
    echo '<table class="overview-table"><thead><tr><th class="player-column">Игрок</th><th>Прокачка по уровням</th></tr></thead><tbody>';
    foreach ($players as $player) {
        echo '<tr><td>';
        mt_render_player_cell($player, $heroes);
        echo '</td><td><div class="mt-ability-seq">';
        $upgrades = is_array($player['ability_upgrades_arr'] ?? null) ? $player['ability_upgrades_arr'] : [];
        if (empty($upgrades)) {
            echo '<span class="mt-muted">Нет данных</span>';
        } else {
            $level = 0;
            foreach ($upgrades as $ability_id) {
                $level++;
                echo '<span class="mt-ability-step" title="Уровень ' . $level . ' · ability id ' . e((string) $ability_id) . '">' . $level . '</span>';
            }
        }
        echo '</div></td></tr>';
    }
    echo '</tbody></table>';
}

/* ------------------------------------------------------------------ */
/* ЦЕЛИ и ЖУРНАЛ                                                  */
/* ------------------------------------------------------------------ */

function mt_building_name(string $key): string
{
    $side = '';
    if (strpos($key, 'goodguys') !== false) {
        $side = 'Света';
    } elseif (strpos($key, 'badguys') !== false) {
        $side = 'Тьмы';
    }
    $lane = '';
    if (strpos($key, 'top') !== false) {
        $lane = 'верх';
    } elseif (strpos($key, 'mid') !== false) {
        $lane = 'центр';
    } elseif (strpos($key, 'bot') !== false) {
        $lane = 'низ';
    }
    if (strpos($key, 'tower') !== false) {
        $type = 'Башня';
    } elseif (strpos($key, 'rax') !== false || strpos($key, 'melee') !== false || strpos($key, 'range') !== false) {
        $type = 'Барак';
    } elseif (strpos($key, 'fort') !== false) {
        $type = 'Трон';
    } else {
        $type = 'Строение';
    }
    return trim($type . ' ' . $side . ($lane !== '' ? ' (' . $lane . ')' : ''));
}

function mt_objective_label(array $obj): string
{
    $type = (string) ($obj['type'] ?? '');
    switch ($type) {
        case 'CHAT_MESSAGE_FIRSTBLOOD':
            return 'Первая кровь';
        case 'CHAT_MESSAGE_ROSHAN_KILL':
            $team = (int) ($obj['team'] ?? 0);
            $by = $team === 2 ? 'Свет' : ($team === 3 ? 'Тьма' : '');
            return 'Убит Рошан' . ($by !== '' ? ' (' . $by . ')' : '');
        case 'CHAT_MESSAGE_AEGIS':
            return 'Подобран Аегис';
        case 'CHAT_MESSAGE_AEGIS_STOLEN':
            return 'Аегис украден';
        case 'CHAT_MESSAGE_DENIED_AEGIS':
            return 'Аегис уничтожен';
        case 'CHAT_MESSAGE_COURIER_LOST':
            return 'Потерян курьер';
        case 'building_kill':
            return 'Разрушено: ' . mt_building_name((string) ($obj['key'] ?? ''));
        case 'CHAT_MESSAGE_TOWER_KILL':
        case 'CHAT_MESSAGE_TOWER_DENY':
            return 'Башня уничтожена';
        default:
            return $type !== '' ? $type : 'Событие';
    }
}

function render_objectives_tab(array $match): void
{
    $objectives = is_array($match['objectives'] ?? null) ? $match['objectives'] : [];
    if (empty($objectives)) {
        mt_empty_state('События по целям доступны только для обработанных матчей.');
        return;
    }
    usort($objectives, fn($a, $b) => ((int) ($a['time'] ?? 0)) <=> ((int) ($b['time'] ?? 0)));
    $names = mt_slot_name_map($match);
    mt_team_header('Цели', '', false, 'Ключевые события матча');
    echo '<table class="overview-table"><thead><tr><th class="col-center">Время</th><th>Событие</th><th>Игрок</th></tr></thead><tbody>';
    foreach ($objectives as $obj) {
        if (!is_array($obj)) {
            continue;
        }
        $time = (int) ($obj['time'] ?? 0);
        $slot = $obj['player_slot'] ?? null;
        $player = $slot !== null && isset($names[(int) $slot]) ? $names[(int) $slot] : '';
        echo '<tr><td class="col-center">' . e(mt_format_time($time)) . '</td>';
        echo '<td>' . e(mt_objective_label($obj)) . '</td>';
        echo '<td>' . e($player) . '</td></tr>';
    }
    echo '</tbody></table>';
}

function render_log_tab(array $match): void
{
    $events = [];
    foreach ((is_array($match['objectives'] ?? null) ? $match['objectives'] : []) as $obj) {
        if (!is_array($obj)) {
            continue;
        }
        $events[] = ['time' => (int) ($obj['time'] ?? 0), 'text' => mt_objective_label($obj), 'kind' => 'objective'];
    }
    foreach ((is_array($match['teamfights'] ?? null) ? $match['teamfights'] : []) as $tf) {
        if (!is_array($tf)) {
            continue;
        }
        $deaths = (int) ($tf['deaths'] ?? 0);
        $events[] = [
            'time' => (int) ($tf['start'] ?? 0),
            'text' => 'Командный бой · смертей: ' . $deaths,
            'kind' => 'teamfight',
        ];
    }
    if (empty($events)) {
        mt_empty_state('Журнал событий доступен только для обработанных матчей.');
        return;
    }
    usort($events, fn($a, $b) => $a['time'] <=> $b['time']);
    mt_team_header('Журнал', '', false, 'Хронология матча');
    echo '<div class="mt-feed">';
    foreach ($events as $event) {
        echo '<div class="mt-feed-item mt-' . e($event['kind']) . '">';
        echo '<span class="mt-time">' . e(mt_format_time($event['time'])) . '</span>';
        echo '<span class="mt-feed-text">' . e($event['text']) . '</span>';
        echo '</div>';
    }
    echo '</div>';
}

/* ------------------------------------------------------------------ */
/* ЧАТ                                                              */
/* ------------------------------------------------------------------ */

function render_chat_tab(array $match): void
{
    $chat = is_array($match['chat'] ?? null) ? $match['chat'] : [];
    $messages = [];
    foreach ($chat as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $type = (string) ($entry['type'] ?? '');
        if ($type !== 'chat' && $type !== 'chatwheel') {
            continue;
        }
        $messages[] = $entry;
    }
    if (empty($messages)) {
        mt_empty_state('Сообщения чата недоступны для этого матча.');
        return;
    }
    $names = mt_slot_name_map($match);
    mt_team_header('Чат', '', false, 'Сообщения игроков');
    echo '<table class="overview-table"><thead><tr><th class="col-center">Время</th><th>Игрок</th><th>Сообщение</th></tr></thead><tbody>';
    foreach ($messages as $msg) {
        $time = (int) ($msg['time'] ?? 0);
        $slot = $msg['player_slot'] ?? null;
        $author = (string) ($msg['unit'] ?? ($slot !== null && isset($names[(int) $slot]) ? $names[(int) $slot] : '—'));
        $text = (string) ($msg['key'] ?? '');
        echo '<tr><td class="col-center">' . e(mt_format_time($time)) . '</td>';
        echo '<td>' . e($author) . '</td>';
        echo '<td>' . e($text) . '</td></tr>';
    }
    echo '</tbody></table>';
}

/* ------------------------------------------------------------------ */
/* КОМАНДНЫЕ БОИ                                                  */
/* ------------------------------------------------------------------ */

function render_teamfights_tab(array $match, array $heroes): void
{
    $teamfights = is_array($match['teamfights'] ?? null) ? $match['teamfights'] : [];
    if (empty($teamfights)) {
        mt_empty_state('Данные о командных боях доступны только для обработанных матчей.');
        return;
    }
    $match_players = is_array($match['players'] ?? null) ? array_values($match['players']) : [];
    mt_team_header('Командные бои', '', false, 'Крупные столкновения');
    $index = 0;
    foreach ($teamfights as $tf) {
        if (!is_array($tf)) {
            continue;
        }
        $index++;
        $start = (int) ($tf['start'] ?? 0);
        $end = (int) ($tf['end'] ?? 0);
        $deaths = (int) ($tf['deaths'] ?? 0);
        $r_gold = 0;
        $d_gold = 0;
        $fallen = [];
        foreach (($tf['players'] ?? []) as $i => $tp) {
            if (!is_array($tp)) {
                continue;
            }
            $mp = $match_players[$i] ?? null;
            $is_radiant = is_array($mp)
                ? (($mp['isRadiant'] ?? null) ?? ((int) ($mp['player_slot'] ?? 0) < 128))
                : true;
            $gold_delta = (int) ($tp['gold_delta'] ?? 0);
            if ($is_radiant) {
                $r_gold += $gold_delta;
            } else {
                $d_gold += $gold_delta;
            }
            if ((int) ($tp['deaths'] ?? 0) > 0 && is_array($mp)) {
                $fallen[] = $mp;
            }
        }
        echo '<div class="mt-tf-card">';
        echo '<div class="mt-tf-head">';
        echo '<span class="mt-tf-title">Бой #' . $index . '</span>';
        echo '<span class="mt-tf-time">' . e(mt_format_time($start)) . ' – ' . e(mt_format_time($end)) . '</span>';
        echo '</div>';
        echo '<div class="mt-tf-stats">';
        echo '<span>Смертей: <strong>' . $deaths . '</strong></span>';
        echo '<span class="radiant-title">Свет: <strong>' . ($r_gold >= 0 ? '+' : '') . $r_gold . '</strong> зол.</span>';
        echo '<span class="dire-title">Тьма: <strong>' . ($d_gold >= 0 ? '+' : '') . $d_gold . '</strong> зол.</span>';
        echo '</div>';
        if (!empty($fallen)) {
            echo '<div class="mt-tf-fallen"><span class="mt-muted">Погибли:</span>';
            foreach ($fallen as $mp) {
                $img = get_hero_img((int) ($mp['hero_id'] ?? 0), $heroes);
                if ($img) {
                    echo '<img class="hero-img mt-tf-hero" src="' . e($img) . '" alt="" title="' . e(get_hero_name((int) ($mp['hero_id'] ?? 0), $heroes)) . '">';
                }
            }
            echo '</div>';
        }
        echo '</div>';
    }
}

/* ------------------------------------------------------------------ */
/* ГРАФИКИ                                                         */
/* ------------------------------------------------------------------ */

function render_graphs_tab(array $match): void
{
    $gold = is_array($match['radiant_gold_adv'] ?? null) ? array_map('intval', $match['radiant_gold_adv']) : [];
    $xp = is_array($match['radiant_xp_adv'] ?? null) ? array_map('intval', $match['radiant_xp_adv']) : [];
    if (empty($gold) && empty($xp)) {
        mt_empty_state('Графики преимущества доступны только для обработанных матчей.');
        return;
    }
    if (!empty($gold)) {
        mt_line_chart('Преимущество по золоту (Свет − Тьма)', $gold);
    }
    if (!empty($xp)) {
        mt_line_chart('Преимущество по опыту (Свет − Тьма)', $xp);
    }
}

function mt_line_chart(string $title, array $values): void
{
    $n = count($values);
    $width = 920;
    $height = 260;
    $pad = 34;
    $max_abs = 1;
    foreach ($values as $v) {
        $max_abs = max($max_abs, abs((int) $v));
    }
    $mid_y = $height / 2;
    $plot_h = $mid_y - $pad;
    $step = $n > 1 ? ($width - 2 * $pad) / ($n - 1) : 0;

    $points = [];
    foreach ($values as $i => $v) {
        $x = $pad + $i * $step;
        $y = $mid_y - ((int) $v / $max_abs) * $plot_h;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $polyline = implode(' ', $points);
    $last = end($values);
    $peak_radiant = max($values);
    $peak_dire = min($values);

    echo '<div class="team-header"><div><span class="team-title">' . e($title) . '</span></div></div>';
    echo '<div class="mt-chart">';
    echo '<svg viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" role="img">';
    echo '<line x1="' . $pad . '" y1="' . $mid_y . '" x2="' . ($width - $pad) . '" y2="' . $mid_y . '" class="mt-chart-axis" />';
    echo '<polyline points="' . e($polyline) . '" class="mt-chart-line" fill="none" />';
    echo '<text x="' . $pad . '" y="16" class="mt-chart-label">Свет +' . (int) $peak_radiant . '</text>';
    echo '<text x="' . $pad . '" y="' . ($height - 8) . '" class="mt-chart-label">Тьма +' . abs((int) $peak_dire) . '</text>';
    echo '</svg>';
    echo '<div class="mt-chart-foot">На ' . max(0, $n - 1) . '-й минуте: ' . ((int) $last >= 0 ? 'Свет +' . (int) $last : 'Тьма +' . abs((int) $last)) . ' золота/опыта</div>';
    echo '</div>';
}

/* ------------------------------------------------------------------ */
/* ИСТОРИЯ                                                          */
/* ------------------------------------------------------------------ */

function render_story_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $radiant_win = !empty($match['radiant_win']);
    $duration = (int) ($match['duration'] ?? 0);
    $all = array_merge($radiant, $dire);

    $top_kills = null;
    $top_networth = null;
    $top_damage = null;
    foreach ($all as $p) {
        if ($top_kills === null || (int) ($p['kills'] ?? 0) > (int) ($top_kills['kills'] ?? 0)) {
            $top_kills = $p;
        }
        if ($top_networth === null || (int) ($p['net_worth'] ?? $p['total_gold'] ?? 0) > (int) ($top_networth['net_worth'] ?? $top_networth['total_gold'] ?? 0)) {
            $top_networth = $p;
        }
        if ($top_damage === null || (int) ($p['hero_damage'] ?? 0) > (int) ($top_damage['hero_damage'] ?? 0)) {
            $top_damage = $p;
        }
    }

    $hero_of = function (?array $p) use ($heroes): string {
        if (!is_array($p)) {
            return '—';
        }
        $name = (string) ($p['personaname'] ?? $p['name'] ?? 'Аноним');
        return $name . ' (' . get_hero_name((int) ($p['hero_id'] ?? 0), $heroes) . ')';
    };

    $winner = $radiant_win ? 'Силы Света' : 'Силы Тьмы';

    mt_team_header('История матча', $radiant_win ? 'radiant-title' : 'dire-title', false, 'Краткий пересказ');
    echo '<div class="mt-story">';
    echo '<p>Матч длился <strong>' . e(format_vision_time($duration)) . '</strong> и завершился победой <strong>' . e($winner) . '</strong>.</p>';
    echo '<ul>';
    echo '<li>Больше всех убийств: <strong>' . e($hero_of($top_kills)) . '</strong> — ' . (int) ($top_kills['kills'] ?? 0) . '.</li>';
    echo '<li>Самый богатый герой: <strong>' . e($hero_of($top_networth)) . '</strong> — ' . (int) ($top_networth['net_worth'] ?? $top_networth['total_gold'] ?? 0) . ' золота.</li>';
    echo '<li>Больше всего урона по героям: <strong>' . e($hero_of($top_damage)) . '</strong> — ' . (int) ($top_damage['hero_damage'] ?? 0) . '.</li>';
    echo '</ul>';
    echo '</div>';
}

/* ------------------------------------------------------------------ */
/* СНАРЯЖЕНИЕ (косметика)                                    */
/* ------------------------------------------------------------------ */

function render_cosmetics_tab(array $match, array $radiant, array $dire, array $heroes): void
{
    $rows = [];
    foreach (array_merge($radiant, $dire) as $p) {
        $cosmetics = $p['cosmetics'] ?? null;
        if (is_array($cosmetics) && !empty($cosmetics)) {
            $rows[] = [$p, count($cosmetics)];
        }
    }
    if (empty($rows)) {
        mt_empty_state('Данные о снаряжении (косметике) для этого матча недоступны.');
        return;
    }
    mt_team_header('Снаряжение', '', false, 'Предметы внешнего вида');
    echo '<table class="overview-table"><thead><tr><th class="player-column">Игрок</th><th class="col-center">Предметов</th></tr></thead><tbody>';
    foreach ($rows as [$player, $cnt]) {
        echo '<tr><td>';
        mt_render_player_cell($player, $heroes);
        echo '</td><td class="col-center">' . (int) $cnt . '</td></tr>';
    }
    echo '</tbody></table>';
}
