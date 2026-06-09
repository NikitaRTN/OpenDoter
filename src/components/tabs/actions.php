<?php

declare(strict_types=1);

/**
 * Группы действий по enum dota_unit_order_t (оборонительно: неизвестные → Прочее).
 */
function action_category_groups(): array
{
    return [
        'move' => ['label' => 'Передвижение', 'ids' => [1, 2, 3, 10, 21, 28, 29, 33]],
        'ability' => ['label' => 'Способности', 'ids' => [5, 6, 7, 8, 9, 11, 20, 26, 27]],
        'item' => ['label' => 'Предметы', 'ids' => [12, 13, 14, 15, 16, 17, 18, 19, 25, 32]],
        'attack' => ['label' => 'Атака', 'ids' => [4]],
        'other' => ['label' => 'Прочее', 'ids' => [22, 23, 24, 30, 31]],
    ];
}

function render_actions_match_tab(array $players, array $heroes, int $duration_seconds): void
{
    $groups = action_category_groups();
    $id_to_group = [];
    foreach ($groups as $gkey => $g) {
        foreach ($g['ids'] as $id) {
            $id_to_group[$id] = $gkey;
        }
    }
    $minutes = max(1.0, $duration_seconds / 60);

    $rows = [];
    $has_actions = false;
    foreach ($players as $player) {
        if (!is_array($player)) {
            continue;
        }
        $actions = is_array($player['actions'] ?? null) ? $player['actions'] : [];
        $cat = ['move' => 0, 'ability' => 0, 'item' => 0, 'attack' => 0, 'other' => 0];
        $total = 0;
        foreach ($actions as $id => $count) {
            $count = (int) $count;
            $total += $count;
            $gkey = $id_to_group[(int) $id] ?? 'other';
            $cat[$gkey] += $count;
        }
        if ($total > 0) {
            $has_actions = true;
        }
        $apm = $total > 0
            ? (int) round($total / $minutes)
            : (int) round((float) ($player['actions_per_min'] ?? 0));
        $rows[] = ['player' => $player, 'apm' => $apm, 'total' => $total, 'cat' => $cat];
    }

    if ($rows === []) {
        render_match_tab_empty('Данные о действиях недоступны.');
        return;
    }

    if (!$has_actions) {
        $any_apm = false;
        foreach ($rows as $r) {
            if ($r['apm'] > 0) {
                $any_apm = true;
                break;
            }
        }
        if (!$any_apm) {
            render_match_tab_empty('Детальные данные о действиях доступны только для разобранных матчей.');
            return;
        }
    }

    usort($rows, static fn (array $a, array $b): int => $b['apm'] <=> $a['apm']);
    $detailed = $has_actions;
    ?>
    <p class="mb-3 text-sm text-muted">APM — среднее число действий в минуту.<?php echo $detailed ? ' Ниже — из чего складывается активность игрока.' : ''; ?></p>
    <table class="overview-table">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center">APM</th>
                <?php if ($detailed): ?>
                    <th class="col-center">Передвижение</th>
                    <th class="col-center">Способности</th>
                    <th class="col-center">Предметы</th>
                    <th class="col-center">Атака</th>
                    <th class="col-center">Прочее</th>
                    <th class="col-center">Всего</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php render_player_match_cells($row['player'], $heroes); ?>
                <td class="col-center"><strong><?php echo e((string) $row['apm']); ?></strong></td>
                <?php if ($detailed): ?>
                    <td class="col-center"><?php echo e(format_stat($row['cat']['move'])); ?></td>
                    <td class="col-center"><?php echo e(format_stat($row['cat']['ability'])); ?></td>
                    <td class="col-center"><?php echo e(format_stat($row['cat']['item'])); ?></td>
                    <td class="col-center"><?php echo e(format_stat($row['cat']['attack'])); ?></td>
                    <td class="col-center"><?php echo e(format_stat($row['cat']['other'])); ?></td>
                    <td class="col-center"><?php echo e(format_stat($row['total'])); ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
