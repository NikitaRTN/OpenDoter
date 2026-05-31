<?php

declare(strict_types=1);

/**
 * Вкладка "Урон". Две панели:
 *  - разбивка урона (нанесённый по героям, полученный, лечение);
 *  - матрица урона по целям/строениям по каждому игроку.
 */
function render_damage_page(array $players, array $heroes): void
{
    render_match_tab_section(match_tab_title('damage'), 'нанесённый и полученный урон, лечение', static function () use ($players, $heroes): void {
        render_damage_breakdown_table($players, $heroes);
    });

    render_match_tab_section('Урон по целям', 'урон по строениям и Рошану по игрокам', static function () use ($players, $heroes): void {
        render_damage_targets_matrix($players, $heroes);
    });
}

function sum_int_map(mixed $map): int
{
    if (!is_array($map)) {
        return 0;
    }

    $sum = 0;
    foreach ($map as $value) {
        $sum += (int) $value;
    }

    return $sum;
}

function render_damage_inline_bar(int $value, int $max): string
{
    $percent = $max > 0 ? (int) round($value / $max * 100) : 0;

    return '<div style="display:flex;align-items:center;gap:8px;min-width:120px;">'
        . '<div style="flex:1;height:8px;border-radius:3px;background:rgba(255,255,255,0.08);overflow:hidden;">'
        . '<span style="display:block;height:100%;border-radius:3px;background:#f1c40f;width:' . $percent . '%;"></span>'
        . '</div>'
        . '<span style="min-width:42px;text-align:right;font-weight:bold;color:#f1c40f;white-space:nowrap;">' . e(format_stat($value)) . '</span>'
        . '</div>';
}

function damage_cell_style(int $value, int $max): string
{
    if ($value <= 0 || $max <= 0) {
        return '';
    }

    $percent = (int) round($value / $max * 100);

    return ' style="background:linear-gradient(to right, rgba(241,196,15,0.28) ' . $percent . '%, transparent ' . $percent . '%);"';
}

function render_damage_breakdown_table(array $players, array $heroes): void
{
    $rows = [];
    $max_dealt = 0;
    foreach ($players as $player) {
        if (!is_array($player)) {
            continue;
        }

        $dealt = (int) ($player['hero_damage'] ?? 0);
        $taken = sum_int_map($player['damage_taken'] ?? null);
        $healing = (int) ($player['hero_healing'] ?? 0);
        $rows[] = ['player' => $player, 'dealt' => $dealt, 'taken' => $taken, 'healing' => $healing];
        $max_dealt = max($max_dealt, $dealt);
    }

    usort($rows, static fn (array $a, array $b): int => $b['dealt'] <=> $a['dealt']);
    ?>
    <table class="overview-table">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center">Команда</th>
                <th class="col-center">Лечение</th>
                <th class="col-center">Получено</th>
                <th>Урон по героям</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php render_player_match_cells($row['player'], $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($row['player'])); ?>"><?php echo e(player_match_team_label($row['player'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($row['healing'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($row['taken'])); ?></td>
                <td><?php echo render_damage_inline_bar($row['dealt'], $max_dealt); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function aggregate_damage_matrix(mixed $damage): array
{
    $matrix = [
        't1' => 0,
        't2' => 0,
        't3' => 0,
        't4' => 0,
        'rax_melee' => 0,
        'rax_range' => 0,
        'fort' => 0,
        'roshan' => 0,
        'other' => 0,
        'total' => 0,
    ];
    if (!is_array($damage)) {
        return $matrix;
    }

    foreach ($damage as $unit => $value) {
        $unit = (string) $unit;
        $amount = (int) $value;
        if ($amount <= 0) {
            continue;
        }

        if (str_contains($unit, 'tower')) {
            $tier = preg_match('/tower(\d)/', $unit, $matches) ? (int) $matches[1] : 0;
            $key = match ($tier) {
                1 => 't1',
                2 => 't2',
                3 => 't3',
                4 => 't4',
                default => 'other',
            };
            $matrix[$key] += $amount;
        } elseif (str_contains($unit, 'melee_rax')) {
            $matrix['rax_melee'] += $amount;
        } elseif (str_contains($unit, 'range_rax')) {
            $matrix['rax_range'] += $amount;
        } elseif (str_contains($unit, 'rax')) {
            $matrix['rax_melee'] += $amount;
        } elseif (str_contains($unit, 'fort')) {
            $matrix['fort'] += $amount;
        } elseif (str_contains($unit, 'roshan')) {
            $matrix['roshan'] += $amount;
        } elseif (str_contains($unit, 'healers') || str_contains($unit, 'watch_tower') || str_contains($unit, 'miniboss') || str_contains($unit, 'filler')) {
            $matrix['other'] += $amount;
        } else {
            continue;
        }

        $matrix['total'] += $amount;
    }

    return $matrix;
}

function render_damage_targets_matrix(array $players, array $heroes): void
{
    $columns = [
        't1' => 'Башня T1',
        't2' => 'Башня T2',
        't3' => 'Башня T3',
        't4' => 'Башня T4',
        'rax_melee' => 'Казармы бл.',
        'rax_range' => 'Казармы дал.',
        'fort' => 'Трон',
        'roshan' => 'Рошан',
        'other' => 'Прочее',
    ];

    $rows = [];
    $col_max = array_fill_keys(array_keys($columns), 0);
    $col_max['total'] = 0;
    $grand_total = 0;

    foreach ($players as $player) {
        if (!is_array($player)) {
            continue;
        }

        $matrix = aggregate_damage_matrix($player['damage'] ?? null);
        foreach ($columns as $key => $label) {
            $col_max[$key] = max($col_max[$key], $matrix[$key]);
        }
        $col_max['total'] = max($col_max['total'], $matrix['total']);
        $grand_total += $matrix['total'];
        $rows[] = ['player' => $player, 'matrix' => $matrix];
    }

    if ($grand_total <= 0) {
        render_match_tab_empty('Детального урона по строениям нет — матч не распарсен.');
        return;
    }

    usort($rows, static fn (array $a, array $b): int => $b['matrix']['total'] <=> $a['matrix']['total']);
    ?>
    <table class="overview-table">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center">Команда</th>
                <?php foreach ($columns as $label): ?><th class="col-center"><?php echo e($label); ?></th><?php endforeach; ?>
                <th class="col-center">Всего</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php $matrix = $row['matrix']; ?>
            <tr>
                <?php render_player_match_cells($row['player'], $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($row['player'])); ?>"><?php echo e(player_match_team_label($row['player'])); ?></td>
                <?php foreach ($columns as $key => $label): ?>
                    <td class="col-center"<?php echo damage_cell_style($matrix[$key], $col_max[$key]); ?>><?php echo e(format_stat($matrix[$key])); ?></td>
                <?php endforeach; ?>
                <td class="col-center"<?php echo damage_cell_style($matrix['total'], $col_max['total']); ?>><strong><?php echo e(format_stat($matrix['total'])); ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
