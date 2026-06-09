<?php

declare(strict_types=1);

/**
 * Детальная вкладка урона.
 *
 * OpenDota хранит у каждого игрока карты:
 *   - `damage`        — нанесённый урон по целям (npc → число),
 *     включая вражеских героев и строения (башни, казармы, форт, Рошан).
 *   - `damage_taken`  — полученный урон (источник npc → число).
 *
 * Отсюда строим матрицы «игрок × вражеский герой» и «игрок × строение».
 */

function match_has_damage_breakdown(array $players): bool
{
    foreach ($players as $player) {
        if (!empty($player['damage']) && is_array($player['damage'])) {
            return true;
        }
    }

    return false;
}

function player_hero_npc(array $player, array $heroes): string
{
    $hero_id = (int) ($player['hero_id'] ?? 0);
    return (string) ($heroes[$hero_id]['name'] ?? '');
}

function damage_building_columns(): array
{
    return ['T1', 'T2', 'T3', 'T4', 'Казармы', 'Крепость', 'Рошан'];
}

function damage_building_category(string $key): ?string
{
    if (str_contains($key, 'fort')) {
        return 'Крепость';
    }
    if (str_contains($key, 'roshan')) {
        return 'Рошан';
    }
    if (str_contains($key, 'rax')) {
        return 'Казармы';
    }
    if (preg_match('/tower(\d)/', $key, $matches) === 1) {
        return 'T' . $matches[1];
    }

    return null;
}

function damage_building_title(string $column): string
{
    return [
        'T1' => 'Башни 1-го уровня',
        'T2' => 'Башни 2-го уровня',
        'T3' => 'Башни 3-го уровня',
        'T4' => 'Башни 4-го уровня',
        'Казармы' => 'Казармы (ближние и дальние)',
        'Крепость' => 'Трон / Крепость',
        'Рошан' => 'Урон по Рошану',
    ][$column] ?? $column;
}

function render_hero_damage_matrix(string $team_label, array $row_players, array $enemy_players, array $heroes, string $field, string $tone): void
{
    if ($row_players === [] || $enemy_players === []) {
        return;
    }

    $max = 0;
    foreach ($row_players as $row_player) {
        $map = is_array($row_player[$field] ?? null) ? $row_player[$field] : [];
        foreach ($enemy_players as $enemy_player) {
            $value = (int) ($map[player_hero_npc($enemy_player, $heroes)] ?? 0);
            if ($value > $max) {
                $max = $value;
            }
        }
    }
    $max = max(1, $max);
    $team_class = player_match_team_class($row_players[0]);
    ?>
    <div class="team-header"><span class="team-title <?php echo e($team_class); ?>"><?php echo e($team_label); ?></span></div>
    <table class="overview-table damage-matrix">
        <thead>
            <tr>
                <th class="player-column">Игрок</th>
                <?php foreach ($enemy_players as $enemy_player): ?>
                    <?php
                    $enemy_id = (int) ($enemy_player['hero_id'] ?? 0);
                    $enemy_img = get_hero_img($enemy_id, $heroes);
                    $enemy_name = get_hero_name($enemy_id, $heroes);
                    ?>
                    <th class="col-center dmg-hero-col" title="<?php echo e($enemy_name); ?>">
                        <?php if ($enemy_img): ?><img class="dmg-hero-icon" src="<?php echo e($enemy_img); ?>" alt="<?php echo e($enemy_name); ?>"><?php else: ?><?php echo e($enemy_name); ?><?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <th class="col-center">Всего</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($row_players as $row_player): ?>
            <?php
            $map = is_array($row_player[$field] ?? null) ? $row_player[$field] : [];
            $row_total = 0;
            ?>
            <tr>
                <?php render_player_match_cells($row_player, $heroes); ?>
                <?php foreach ($enemy_players as $enemy_player): ?>
                    <?php
                    $value = (int) ($map[player_hero_npc($enemy_player, $heroes)] ?? 0);
                    $row_total += $value;
                    $width = (int) round($value / $max * 100);
                    ?>
                    <td class="col-center dmg-cell" title="<?php echo e(number_format($value)); ?>">
                        <span class="dmg-val"><?php echo e($value > 0 ? format_stat($value) : '-'); ?></span>
                        <span class="dmg-bar"><span class="dmg-bar-fill tone-<?php echo e($tone); ?>" style="width: <?php echo e((string) $width); ?>%;"></span></span>
                    </td>
                <?php endforeach; ?>
                <td class="col-center" title="<?php echo e(number_format($row_total)); ?>"><strong><?php echo e($row_total > 0 ? format_stat($row_total) : '-'); ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function render_building_damage_matrix(string $team_label, array $players, array $heroes): void
{
    if ($players === []) {
        return;
    }

    $columns = damage_building_columns();
    $rows = [];
    $col_max = array_fill_keys($columns, 0);

    foreach ($players as $player) {
        $map = is_array($player['damage'] ?? null) ? $player['damage'] : [];
        $agg = array_fill_keys($columns, 0);
        foreach ($map as $key => $amount) {
            $category = damage_building_category((string) $key);
            if ($category !== null && array_key_exists($category, $agg)) {
                $agg[$category] += (int) $amount;
            }
        }
        foreach ($columns as $column) {
            if ($agg[$column] > $col_max[$column]) {
                $col_max[$column] = $agg[$column];
            }
        }
        $rows[] = ['player' => $player, 'agg' => $agg, 'total' => array_sum($agg)];
    }

    usort($rows, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
    $team_class = player_match_team_class($players[0]);
    ?>
    <div class="damage-team-block <?php echo e($team_class); ?>">
        <div class="team-header damage-team-header">
            <span class="team-title <?php echo e($team_class); ?>"><?php echo e($team_label); ?></span>
            <span class="team-subtitle">урон этой команды по строениям и целям</span>
        </div>
        <table class="overview-table damage-matrix damage-building-table">
            <thead>
                <tr>
                    <th class="player-column">Игрок</th>
                    <?php foreach ($columns as $column): ?><th class="col-center" title="<?php echo e(damage_building_title($column)); ?>"><?php echo e($column); ?></th><?php endforeach; ?>
                    <th class="col-center">Всего</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php render_player_match_cells($row['player'], $heroes); ?>
                    <?php foreach ($columns as $column): ?>
                        <?php
                        $value = (int) $row['agg'][$column];
                        $column_max = max(1, (int) $col_max[$column]);
                        $width = (int) round($value / $column_max * 100);
                        ?>
                        <td class="col-center dmg-cell" title="<?php echo e(number_format($value)); ?>">
                            <span class="dmg-val"><?php echo e($value > 0 ? format_stat($value) : '-'); ?></span>
                            <span class="dmg-bar"><span class="dmg-bar-fill tone-building" style="width: <?php echo e((string) $width); ?>%;"></span></span>
                        </td>
                    <?php endforeach; ?>
                    <td class="col-center" title="<?php echo e(number_format($row['total'])); ?>"><strong><?php echo e($row['total'] > 0 ? format_stat($row['total']) : '-'); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function render_damage_tab(array $radiant_players, array $dire_players, array $heroes): void
{
    $players = array_merge($radiant_players, $dire_players);

    if (!match_has_damage_breakdown($players)) {
        render_match_tab_section(match_tab_title('damage'), 'урон и лечение', static function () use ($players, $heroes): void {
            render_match_tab_empty('Детальная разбивка урона недоступна: матч не распарсен. Показаны базовые суммы:');
            render_metric_match_tab($players, $heroes, [
                'hero_damage' => 'Урон по героям',
                'tower_damage' => 'Урон по строениям',
                'hero_healing' => 'Лечение',
            ]);
        });

        return;
    }

    render_match_tab_section('Урон по вражеским героям', 'нанесённый урон, игрок против вражеских героев', static function () use ($radiant_players, $dire_players, $heroes): void {
        render_hero_damage_matrix('Свет', $radiant_players, $dire_players, $heroes, 'damage', 'dealt');
        render_hero_damage_matrix('Тьма', $dire_players, $radiant_players, $heroes, 'damage', 'dealt');
    });

    render_match_tab_section('Полученный урон от героев', 'сколько игрок получил от вражеских героев', static function () use ($radiant_players, $dire_players, $heroes): void {
        render_hero_damage_matrix('Свет', $radiant_players, $dire_players, $heroes, 'damage_taken', 'received');
        render_hero_damage_matrix('Тьма', $dire_players, $radiant_players, $heroes, 'damage_taken', 'received');
    });

    render_match_tab_section('Урон по строениям и целям', 'разделено по командам: башни, казармы, Крепость и Рошан', static function () use ($radiant_players, $dire_players, $heroes): void {
        render_building_damage_matrix('Свет', $radiant_players, $heroes);
        render_building_damage_matrix('Тьма', $dire_players, $heroes);
    });
}

function render_damage_match_tab(array $players, array $heroes): void
{
    // Совместимость: разделяем список обратно по командам.
    $radiant = [];
    $dire = [];
    foreach ($players as $player) {
        if (player_match_team_label($player) === 'Свет') {
            $radiant[] = $player;
        } else {
            $dire[] = $player;
        }
    }
    render_damage_tab($radiant, $dire, $heroes);
}
