<?php

declare(strict_types=1);

/**
 * Вкладка «Заработок» (/gold).
 *
 * Базовые показатели (нетворс, GPM, добито, потрачено) доступны всегда. График
 * перевеса по золоту/опыту во времени и разбор источников золота требуют
 * распарсенного реплея, поэтому прячутся за общий detailed_stats_gate.
 */
function render_gold_page(array $match, array $players, array $heroes): void
{
    $match_id = (string) ($match['match_id'] ?? '');

    $gold_adv = gold_normalize_series($match['radiant_gold_adv'] ?? null);
    $xp_adv   = gold_normalize_series($match['radiant_xp_adv'] ?? null);
    $has_adv  = count($gold_adv) >= 2 || count($xp_adv) >= 2;

    $has_sources = false;
    foreach ($players as $player) {
        if (gold_reasons_total($player['gold_reasons'] ?? null) > 0) {
            $has_sources = true;
            break;
        }
    }

    $has_detailed = $has_adv || $has_sources;

    // Нетворс и фарм — всегда доступны из базовых данных матча.
    render_gold_networth_section($players, $heroes);

    // Детальная часть (графики и источники) живёт за гейтом распарсенных данных.
    render_detailed_stats_gate($match_id, 'графиков перевеса и источников золота', $has_detailed);
    ?>
    <div data-stats-content <?php echo $has_detailed ? '' : 'hidden'; ?>>
        <?php if ($has_adv): ?>
            <?php render_match_tab_section('Перевес во времени', 'нетворс и опыт по минутам', static function () use ($gold_adv, $xp_adv): void {
                render_gold_advantage_charts($gold_adv, $xp_adv);
            }); ?>
        <?php endif; ?>
        <?php if ($has_sources): ?>
            <?php render_match_tab_section('Источники золота', 'откуда заработано и сколько потрачено', static function () use ($players, $heroes): void {
                render_gold_sources_table($players, $heroes);
            }); ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Обратная совместимость: прежний вызов render_gold_match_tab($players, $heroes)
 * (без объекта матча) по-прежнему рисует таблицу нетворса.
 */
function render_gold_match_tab(array $players, array $heroes): void
{
    render_gold_networth_section($players, $heroes);
}

function render_gold_networth_section(array $players, array $heroes): void
{
    render_match_tab_section(match_tab_title('gold'), 'нетворс и фарм команд', static function () use ($players, $heroes): void {
        render_gold_networth_table($players, $heroes);
    });
}

function render_gold_networth_table(array $players, array $heroes): void
{
    usort($players, static fn (array $a, array $b): int => ((int) ($b['total_gold'] ?? 0)) <=> ((int) ($a['total_gold'] ?? 0)));
    ?>
    <table class="overview-table">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center">Команда</th>
                <th class="col-center">Нетворс</th>
                <th class="col-center">GPM</th>
                <th class="col-center">Потрачено</th>
                <th class="col-center">Добито</th>
                <th class="col-center">Денаи</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($players as $player): ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <td class="col-center"><?php echo e(format_stat($player['total_gold'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e(format_stat($player['gold_per_min'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e(format_stat($player['gold_spent'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e(format_stat($player['last_hits'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e(format_stat($player['denies'] ?? 0)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function render_gold_advantage_charts(array $gold_adv, array $xp_adv): void
{
    if (count($gold_adv) >= 2) {
        render_gold_advantage_chart('Перевес по золоту', $gold_adv);
    }
    if (count($xp_adv) >= 2) {
        render_gold_advantage_chart('Перевес по опыту', $xp_adv);
    }
}

function render_gold_advantage_chart(string $title, array $series): void
{
    $final   = (int) end($series);
    $minutes = count($series) - 1;
    $leader  = $final > 0 ? 'radiant' : ($final < 0 ? 'dire' : 'draw');
    $summary = $final === 0
        ? 'Равенство к концу матча'
        : 'К концу: ' . ($final > 0 ? 'Свет' : 'Тьма') . ' +' . gold_format_amount(abs($final));
    ?>
    <div class="gold-adv">
        <div class="gold-adv-head">
            <span class="gold-adv-title"><?php echo e($title); ?></span>
            <span class="gold-adv-final <?php echo e($leader); ?>"><?php echo e($summary); ?></span>
        </div>
        <div class="gold-adv-wrap">
            <span class="gold-adv-tag top">▲ Свет впереди</span>
            <?php echo gold_advantage_svg($series); ?>
            <span class="gold-adv-tag bottom">▼ Тьма впереди</span>
        </div>
        <div class="gold-adv-axis">
            <span>0 мин</span>
            <span><?php echo e((string) intdiv($minutes, 2)); ?> мин</span>
            <span><?php echo e((string) $minutes); ?> мин</span>
        </div>
    </div>
    <?php
}

function gold_advantage_svg(array $series): string
{
    $count = count($series);
    if ($count < 2) {
        return '';
    }

    $max_abs = 1;
    foreach ($series as $value) {
        $max_abs = max($max_abs, abs((int) $value));
    }

    $w      = 960;
    $h      = 200;
    $pad_x  = 12;
    $pad_y  = 16;
    $innerW = $w - 2 * $pad_x;
    $innerH = $h - 2 * $pad_y;
    $mid    = $pad_y + $innerH / 2;
    $halfH  = $innerH / 2;

    $pts = [];
    foreach ($series as $i => $value) {
        $x = $pad_x + ($innerW * $i / ($count - 1));
        $y = $mid - $halfH * ((int) $value / $max_abs);
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $line = implode(' ', $pts);
    $area = $pad_x . ',' . $mid . ' ' . $line . ' ' . round($pad_x + $innerW, 1) . ',' . $mid;

    $final     = (int) end($series);
    $end_x     = round($pad_x + $innerW, 1);
    $end_y     = round($mid - $halfH * ($final / $max_abs), 1);
    $end_color = $final >= 0 ? '#2ecc71' : '#e74c3c';

    $svg  = '<svg class="gold-adv-svg" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-hidden="true">';
    $svg .= '<rect x="' . $pad_x . '" y="' . $pad_y . '" width="' . $innerW . '" height="' . $halfH . '" fill="rgba(46,204,113,0.08)"/>';
    $svg .= '<rect x="' . $pad_x . '" y="' . $mid . '" width="' . $innerW . '" height="' . $halfH . '" fill="rgba(231,76,60,0.08)"/>';
    $svg .= '<polygon points="' . $area . '" fill="rgba(241,196,15,0.14)"/>';
    $svg .= '<line x1="' . $pad_x . '" y1="' . $mid . '" x2="' . ($w - $pad_x) . '" y2="' . $mid . '" stroke="rgba(214,217,220,0.30)" stroke-width="1" stroke-dasharray="4 4"/>';
    $svg .= '<polyline points="' . $line . '" fill="none" stroke="#f1c40f" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';
    $svg .= '<circle cx="' . $end_x . '" cy="' . $end_y . '" r="3.6" fill="' . $end_color . '"/>';
    $svg .= '</svg>';

    return $svg;
}

function render_gold_sources_table(array $players, array $heroes): void
{
    usort($players, static fn (array $a, array $b): int => ((int) ($b['total_gold'] ?? 0)) <=> ((int) ($a['total_gold'] ?? 0)));
    ?>
    <table class="overview-table gold-sources-table">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center">Команда</th>
                <th>Структура заработка</th>
                <th class="col-center">Крипы</th>
                <th class="col-center">Герои</th>
                <th class="col-center">Строения</th>
                <th class="col-center">Прочее</th>
                <th class="col-center">Потрачено</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($players as $player):
            $groups = gold_reason_groups($player['gold_reasons'] ?? null);
            $sum    = $groups['creeps'] + $groups['heroes'] + $groups['structures'] + $groups['other'];
        ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <td class="gold-bar-cell"><?php echo gold_sources_bar($groups, $sum); ?></td>
                <td class="col-center"><?php echo e(format_stat($groups['creeps'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($groups['heroes'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($groups['structures'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($groups['other'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($player['gold_spent'] ?? 0)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="gold-legend">
        <span class="gold-legend-item"><i class="gold-dot creeps"></i>Крипы и лес</span>
        <span class="gold-legend-item"><i class="gold-dot heroes"></i>Герои и Рошан</span>
        <span class="gold-legend-item"><i class="gold-dot structures"></i>Строения</span>
        <span class="gold-legend-item"><i class="gold-dot other"></i>Прочее</span>
    </div>
    <?php
}

/**
 * Группирует gold_reasons OpenDota в укрупнённые категории.
 * 13 — крипы, 14 — нейтралы; 12 — герои, 15 — Рошан; 11 — строения;
 * остальное (выкуп, продажа предметов и т.п.) попадает в «Прочее».
 */
function gold_reason_groups(mixed $reasons): array
{
    $groups = ['creeps' => 0, 'heroes' => 0, 'structures' => 0, 'other' => 0];
    if (!is_array($reasons)) {
        return $groups;
    }
    foreach ($reasons as $key => $value) {
        if (!is_numeric($value) || (int) $value <= 0) {
            continue;
        }
        $amount = (int) $value;
        $id = (int) preg_replace('/\\D/', '', (string) $key);
        if ($id === 13 || $id === 14) {
            $groups['creeps'] += $amount;
        } elseif ($id === 12 || $id === 15) {
            $groups['heroes'] += $amount;
        } elseif ($id === 11) {
            $groups['structures'] += $amount;
        } else {
            $groups['other'] += $amount;
        }
    }
    return $groups;
}

function gold_sources_bar(array $groups, int $sum): string
{
    if ($sum <= 0) {
        return '<div class="gold-bar empty"></div>';
    }
    $labels = [
        'creeps' => 'Крипы и лес',
        'heroes' => 'Герои и Рошан',
        'structures' => 'Строения',
        'other' => 'Прочее',
    ];
    $html = '<div class="gold-bar">';
    foreach ($labels as $key => $label) {
        $value = (int) $groups[$key];
        if ($value <= 0) {
            continue;
        }
        $pct = round($value / $sum * 100, 2);
        $title = htmlspecialchars($label . ': ' . gold_format_amount($value), ENT_QUOTES);
        $html .= '<span class="gold-bar-seg ' . $key . '" style="width:' . $pct . '%" title="' . $title . '"></span>';
    }
    $html .= '</div>';
    return $html;
}

function gold_normalize_series(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $value) {
        if (is_numeric($value)) {
            $out[] = (int) $value;
        }
    }
    return $out;
}

function gold_reasons_total(mixed $reasons): int
{
    if (!is_array($reasons)) {
        return 0;
    }
    $total = 0;
    foreach ($reasons as $value) {
        if (is_numeric($value) && (int) $value > 0) {
            $total += (int) $value;
        }
    }
    return $total;
}

function gold_format_amount(int $value): string
{
    if (abs($value) >= 1000) {
        return number_format($value / 1000, 1, '.', '') . 'k';
    }
    return (string) $value;
}
