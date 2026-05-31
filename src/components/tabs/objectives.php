<?php

declare(strict_types=1);

/**
 * Вкладка "Цели". Состоит из трёх панелей:
 *  - ключевые события матча (читаемый журнал objective-событий);
 *  - урон по целям (урон по строениям/Рошану по игрокам, с барами);
 *  - руны (собранные руны по типам и игрокам).
 */
function render_objectives_page(array $match, array $radiant_players, array $dire_players, array $heroes): void
{
    $players = array_merge($radiant_players, $dire_players);

    render_match_tab_section(match_tab_title('objectives'), 'ключевые события матча', static function () use ($match, $players): void {
        render_objective_events_table($match['objectives'] ?? null, $players);
    });

    render_match_tab_section('Урон по целям', 'урон по строениям, Рошану и аванпостам', static function () use ($players, $heroes): void {
        render_structure_damage_table($players, $heroes);
    });

    render_match_tab_section('Руны', 'собранные руны по типам', static function () use ($players, $heroes): void {
        render_rune_table($players, $heroes);
    });
}

function index_players_by_slot(array $players): array
{
    $index = [];
    foreach ($players as $player) {
        if (is_array($player) && isset($player['player_slot'])) {
            $index[(int) $player['player_slot']] = $player;
        }
    }

    return $index;
}

function render_objective_events_table(mixed $objectives, array $players): void
{
    if (!is_array($objectives) || $objectives === []) {
        render_match_tab_empty('В данных матча нет событий целей. Скорее всего, матч ещё не распарсен.');
        return;
    }

    $objectives = array_values(array_filter($objectives, 'is_array'));
    usort($objectives, static fn (array $a, array $b): int => ((int) ($a['time'] ?? 0)) <=> ((int) ($b['time'] ?? 0)));
    $slot_index = index_players_by_slot($players);
    ?>
    <table class="overview-table">
        <thead><tr><th class="col-center">Время</th><th>Событие</th><th>Кто</th></tr></thead>
        <tbody>
        <?php foreach ($objectives as $event): ?>
            <?php $info = describe_objective_event($event, $slot_index); ?>
            <tr>
                <td class="col-center"><?php echo e(format_match_tab_time((int) ($event['time'] ?? 0))); ?></td>
                <td><?php echo e($info['label']); ?></td>
                <td><?php echo e($info['actor']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function describe_objective_event(array $event, array $slot_index): array
{
    $type = (string) ($event['type'] ?? '');
    $slot = $event['player_slot'] ?? $event['slot'] ?? null;
    $actor = '';
    if ($slot !== null && isset($slot_index[(int) $slot])) {
        $player = $slot_index[(int) $slot];
        $actor = (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним');
    }

    switch ($type) {
        case 'CHAT_MESSAGE_FIRSTBLOOD':
            return ['label' => 'Первая кровь', 'actor' => $actor];
        case 'CHAT_MESSAGE_ROSHAN_KILL':
            $team = (int) ($event['team'] ?? 0);
            return ['label' => 'Убийство Рошана', 'actor' => $team === 2 ? 'Силы Света' : 'Силы Тьмы'];
        case 'CHAT_MESSAGE_AEGIS':
            return ['label' => 'Подобран Аегис', 'actor' => $actor];
        case 'CHAT_MESSAGE_AEGIS_STOLEN':
            return ['label' => 'Аегис украден', 'actor' => $actor];
        case 'CHAT_MESSAGE_DENIED_AEGIS':
            return ['label' => 'Аегис уничтожен', 'actor' => $actor];
        case 'CHAT_MESSAGE_COURIER_LOST':
            return ['label' => 'Уничтожен курьер', 'actor' => $actor];
        case 'building_kill':
            $unit = (string) ($event['key'] ?? $event['unit'] ?? '');
            return ['label' => 'Разрушено строение: ' . humanize_building_name($unit), 'actor' => $actor];
        default:
            $key = (string) ($event['key'] ?? '');
            $label = $type !== '' ? $type : 'Событие';
            if ($key !== '') {
                $label .= ' (' . $key . ')';
            }
            return ['label' => $label, 'actor' => $actor];
    }
}

function humanize_building_name(string $unit): string
{
    if ($unit === '') {
        return 'строение';
    }

    if (str_contains($unit, 'fort')) {
        $name = 'Трон';
    } elseif (str_contains($unit, 'tower')) {
        $name = 'Башня' . (preg_match('/tower(\d)/', $unit, $matches) ? ' T' . $matches[1] : '');
    } elseif (str_contains($unit, 'melee_rax')) {
        $name = 'Казарма (ближний бой)';
    } elseif (str_contains($unit, 'range_rax')) {
        $name = 'Казарма (дальний бой)';
    } elseif (str_contains($unit, 'rax')) {
        $name = 'Казарма';
    } elseif (str_contains($unit, 'healers')) {
        $name = 'Святилище';
    } elseif (str_contains($unit, 'watch_tower')) {
        $name = 'Аванпост';
    } else {
        $name = $unit;
    }

    $lane = '';
    if (str_contains($unit, '_top')) {
        $lane = ' верх.';
    } elseif (str_contains($unit, '_mid')) {
        $lane = ' центр';
    } elseif (str_contains($unit, '_bot')) {
        $lane = ' ниж.';
    }

    $side = '';
    if (str_contains($unit, 'goodguys')) {
        $side = ' (Свет)';
    } elseif (str_contains($unit, 'badguys')) {
        $side = ' (Тьма)';
    }

    return $name . $lane . $side;
}

function aggregate_structure_damage(mixed $damage): array
{
    $result = ['towers' => 0, 'barracks' => 0, 'fort' => 0, 'roshan' => 0, 'other' => 0, 'total' => 0];
    if (!is_array($damage)) {
        return $result;
    }

    foreach ($damage as $unit => $value) {
        $unit = (string) $unit;
        $amount = (int) $value;
        if ($amount <= 0) {
            continue;
        }

        if (str_contains($unit, 'tower')) {
            $result['towers'] += $amount;
        } elseif (str_contains($unit, 'rax')) {
            $result['barracks'] += $amount;
        } elseif (str_contains($unit, 'fort')) {
            $result['fort'] += $amount;
        } elseif (str_contains($unit, 'roshan')) {
            $result['roshan'] += $amount;
        } elseif (str_contains($unit, 'healers') || str_contains($unit, 'watch_tower') || str_contains($unit, 'miniboss') || str_contains($unit, 'filler')) {
            $result['other'] += $amount;
        } else {
            continue;
        }

        $result['total'] += $amount;
    }

    return $result;
}

function render_structure_damage_table(array $players, array $heroes): void
{
    $rows = [];
    $max_total = 0;
    foreach ($players as $player) {
        if (!is_array($player)) {
            continue;
        }

        $breakdown = aggregate_structure_damage($player['damage'] ?? null);
        $rows[] = ['player' => $player, 'breakdown' => $breakdown];
        $max_total = max($max_total, $breakdown['total']);
    }

    if ($max_total <= 0) {
        render_match_tab_empty('Детального урона по строениям нет — матч не распарсен.');
        return;
    }

    usort($rows, static fn (array $a, array $b): int => $b['breakdown']['total'] <=> $a['breakdown']['total']);
    ?>
    <table class="overview-table">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center">Команда</th>
                <th class="col-center">Башни</th>
                <th class="col-center">Казармы</th>
                <th class="col-center">Трон</th>
                <th class="col-center">Рошан</th>
                <th class="col-center">Прочее</th>
                <th>Всего</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $breakdown = $row['breakdown'];
            $percent = (int) round($breakdown['total'] / $max_total * 100);
            ?>
            <tr>
                <?php render_player_match_cells($row['player'], $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($row['player'])); ?>"><?php echo e(player_match_team_label($row['player'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($breakdown['towers'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($breakdown['barracks'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($breakdown['fort'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($breakdown['roshan'])); ?></td>
                <td class="col-center"><?php echo e(format_stat($breakdown['other'])); ?></td>
                <td>
                    <div class="objective-bar-cell">
                        <div class="objective-bar"><span style="width: <?php echo e($percent); ?>%"></span></div>
                        <span class="objective-bar-value"><?php echo e(format_stat($breakdown['total'])); ?></span>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function rune_type_labels(): array
{
    return [
        0 => 'Двойной урон',
        1 => 'Ускорение',
        2 => 'Иллюзия',
        3 => 'Невидимость',
        4 => 'Регенерация',
        5 => 'Награда',
        6 => 'Тайная',
        7 => 'Водяная',
        8 => 'Мудрость',
    ];
}

function render_rune_table(array $players, array $heroes): void
{
    $labels = rune_type_labels();
    $rows = [];
    $has_data = false;

    foreach ($players as $player) {
        if (!is_array($player)) {
            continue;
        }

        $counts = [];
        $total = 0;
        if (is_array($player['runes'] ?? null)) {
            foreach ($player['runes'] as $type => $count) {
                $counts[(int) $type] = (int) $count;
                $total += (int) $count;
            }
        }

        if ($total > 0) {
            $has_data = true;
        }

        $rows[] = ['player' => $player, 'counts' => $counts, 'total' => $total];
    }

    if (!$has_data) {
        render_match_tab_empty('Данных о собранных рунах нет — матч не распарсен.');
        return;
    }

    usort($rows, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
    ?>
    <table class="overview-table">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center">Команда</th>
                <?php foreach ($labels as $label): ?><th class="col-center"><?php echo e($label); ?></th><?php endforeach; ?>
                <th class="col-center">Всего</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <?php render_player_match_cells($row['player'], $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($row['player'])); ?>"><?php echo e(player_match_team_label($row['player'])); ?></td>
                <?php foreach ($labels as $type => $label): ?>
                    <td class="col-center"><?php echo e($row['counts'][$type] ?? 0); ?></td>
                <?php endforeach; ?>
                <td class="col-center"><?php echo e($row['total']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
