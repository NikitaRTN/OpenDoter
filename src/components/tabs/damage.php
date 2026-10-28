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
    <div class="insight-table-scroll" role="region" aria-label="Матрица урона — <?php echo e($team_label); ?>" tabindex="0"><table class="overview-table damage-matrix">
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
                        <?php if ($enemy_img): ?><img class="dmg-hero-icon" src="<?php echo e($enemy_img); ?>" alt=""><span class="dmg-hero-name"><?php echo e($enemy_name); ?></span><?php else: ?><?php echo e($enemy_name); ?><?php endif; ?>
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
                        <span class="dmg-val"><?php echo e(number_format($value, 0, '.', ' ')); ?></span>
                        <span class="dmg-bar"><span class="dmg-bar-fill tone-<?php echo e($tone); ?>" style="width: <?php echo e((string) $width); ?>%;"></span></span>
                    </td>
                <?php endforeach; ?>
                <td class="col-center" title="<?php echo e(number_format($row_total)); ?>"><strong><?php echo e($row_total > 0 ? number_format($row_total, 0, '.', ' ') : '0'); ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
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
        <div class="insight-table-scroll" role="region" aria-label="Урон по строениям" tabindex="0"><table class="overview-table damage-matrix damage-building-table">
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
                            <span class="dmg-val"><?php echo e(number_format($value, 0, '.', ' ')); ?></span>
                            <span class="dmg-bar"><span class="dmg-bar-fill tone-building" style="width: <?php echo e((string) $width); ?>%;"></span></span>
                        </td>
                    <?php endforeach; ?>
                    <td class="col-center" title="<?php echo e(number_format($row['total'])); ?>"><strong><?php echo e($row['total'] > 0 ? format_stat($row['total']) : '-'); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php
}

function damage_map_total(array $player, string $field, ?array $opponents = null, array $heroes = []): ?int
{
    if (!isset($player[$field]) || !is_array($player[$field])) {
        return null;
    }
    if ($opponents === null) {
        return (int) array_sum($player[$field]);
    }
    $total = 0;
    foreach ($opponents as $enemy) {
        $total += (int) ($player[$field][player_hero_npc($enemy, $heroes)] ?? 0);
    }
    return $total;
}

function damage_number(?int $value): string
{
    return $value === null ? 'Нет данных' : number_format($value, 0, '.', ' ');
}

function render_damage_summary_team(string $team, array $players, array $enemies, array $heroes): void
{
    $rows = [];
    foreach ($players as $player) {
        $rows[] = ['player' => $player, 'dealt' => damage_map_total($player, 'damage', $enemies, $heroes)];
    }
    usort($rows, static fn(array $a, array $b): int => ($b['dealt'] ?? -1) <=> ($a['dealt'] ?? -1));
    ?>
    <section class="insight-team-panel">
        <div class="insight-section-heading"><h3 class="<?php echo $team === 'Свет' ? 'insight-radiant' : 'insight-dire'; ?>"><?php echo e($team); ?></h3><span class="insight-muted">По нанесённому урону героям</span></div>
        <table class="damage-overview-table">
            <thead><tr><th rowspan="2" scope="col">Игрок</th><th colspan="2" scope="colgroup">Нанесённый урон</th><th colspan="2" scope="colgroup">Полученный урон</th><th rowspan="2" scope="col">Лечение</th></tr><tr><th scope="col">Герои</th><th scope="col">Строения</th><th scope="col">Всего по реплею</th><th scope="col">Уменьшено, %</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): $player = $row['player'];
                $taken = damage_map_total($player, 'damage_taken');
                $taken_heroes = damage_map_total($player, 'damage_taken', $enemies, $heroes);
                $buildings = isset($player['tower_damage']) ? (int) $player['tower_damage'] : null;
                $healing = isset($player['hero_healing']) ? (int) $player['hero_healing'] : null;
            ?>
                <tr>
                    <?php render_player_match_cells($player, $heroes); ?>
                    <td data-label="Нанесено героям" class="insight-number damage-dealt"><strong><?php echo e(damage_number($row['dealt'])); ?></strong></td>
                    <td data-label="Нанесено строениям" class="insight-number"><?php echo e(damage_number($buildings)); ?></td>
                    <td data-label="Получено по реплею" class="insight-number damage-taken"><strong><?php echo e(damage_number($taken)); ?></strong><small>От героев: <?php echo e(damage_number($taken_heroes)); ?></small></td>
                    <td data-label="Уменьшено, %" class="damage-unavailable"><span aria-label="Нет данных об уменьшении урона">—</span><small>Нет данных</small></td>
                    <td data-label="Лечение" class="insight-number"><?php echo e(damage_number($healing)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
}

function render_damage_tab(array $radiant_players, array $dire_players, array $heroes): void
{
    $players = array_merge($radiant_players, $dire_players);
    $has_breakdown = match_has_damage_breakdown($players);
    ?>
    <div class="match-insights damage-insights">
        <header class="insight-intro"><span class="insight-eyebrow">РАЗБОР МАТЧА · УРОН</span><h2>Урон и выживаемость</h2><p>Кто наносил урон, кто принимал его на себя и за счёт чего команда продвигалась к победе.</p></header>
        <?php if ($has_breakdown): ?>
        <nav class="insight-jump-links" aria-label="Разделы урона"><a href="#damage-summary">Сводка игроков</a><a href="#damage-breakdowns">Подробная разбивка</a><a href="#damage-explained">Как читать показатели</a></nav>
        <?php endif; ?>
    <?php
    if (!$has_breakdown) {
        echo '<div class="insight-note"><strong>Детальный разбор пока недоступен.</strong> Нужен обработанный реплей. Ниже — только доступные показатели матча; отсутствие данных не означает нулевой урон.</div>';
        echo '<div class="insight-table-scroll" role="region" aria-label="Базовые показатели урона" tabindex="0">';
        echo '<table class="damage-overview-table"><thead><tr><th>Игрок</th><th>Урон героям</th><th>Строения</th><th>Лечение</th></tr></thead><tbody>';
        foreach ($players as $player) {
            echo '<tr>';
            render_player_match_cells($player, $heroes);
            foreach (['hero_damage' => 'Урон героям', 'tower_damage' => 'Строения', 'hero_healing' => 'Лечение'] as $key => $label) {
                echo '<td class="insight-number" data-label="' . e($label) . '">' . e(damage_number(isset($player[$key]) ? (int) $player[$key] : null)) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div></div>';
        return;
    }
    $leaders = [];
    foreach ([['Свет', $radiant_players, $dire_players], ['Тьма', $dire_players, $radiant_players]] as [$team, $roster, $enemies]) {
        foreach ($roster as $player) {
            $leaders[] = ['player' => $player, 'team' => $team, 'dealt' => damage_map_total($player, 'damage', $enemies, $heroes), 'taken' => damage_map_total($player, 'damage_taken')];
        }
    }
    ?>
        <div class="insight-kpis">
        <?php foreach (['dealt' => ['Больше всего урона героям', 'Только вражеские герои, без иллюзий'], 'taken' => ['Получено по реплею: максимум', 'Все источники за матч']] as $key => [$label, $caption]):
            $sorted = array_values(array_filter($leaders, static fn(array $row): bool => $row[$key] !== null));
            usort($sorted, static fn(array $a, array $b): int => $b[$key] <=> $a[$key]);
            $leader = $sorted[0] ?? null;
        ?>
            <div class="insight-kpi"><span class="insight-muted"><?php echo e($label); ?></span><strong><?php echo e(damage_number($leader[$key] ?? null)); ?></strong><span><?php echo $leader ? e(get_hero_name((int) ($leader['player']['hero_id'] ?? 0), $heroes) . ' · ' . $leader['team']) : 'Нет данных'; ?></span><small><?php echo e($caption); ?></small></div>
        <?php endforeach; ?>
            <div class="insight-kpi insight-kpi-note"><span class="insight-muted">Снижение урона</span><strong>Нет данных</strong><span>Не подменяем оценкой брони</span><small>Для процента, как в Dota, нужны исходный и предотвращённый урон.</small></div>
        </div>
        <div id="damage-summary" class="insight-section-heading"><div><h3>Все игроки — в одной сводке</h3><p>Точные значения, без округления до «k». Отдельно — нанесённый и полученный урон.</p></div></div>
        <?php render_damage_summary_team('Свет', $radiant_players, $dire_players, $heroes); render_damage_summary_team('Тьма', $dire_players, $radiant_players, $heroes); ?>
        <div id="damage-breakdowns" class="insight-section-heading"><div><h3>Подробная разбивка</h3><p>Открой нужный раздел, чтобы увидеть конкретные источники и цели.</p></div></div>
        <details class="insight-details"><summary><span><strong>Кому наносили урон</strong><small>Игрок → вражеские герои</small></span><span class="insight-expand" aria-hidden="true">+</span></summary><div class="insight-details-body"><p class="insight-scroll-hint">На узком экране таблицу можно прокручивать по горизонтали.</p><p class="insight-muted">Строка — атакующий игрок. Колонка — герой, которому он нанёс урон. «Всего» — сумма по этим пяти героям. Полосы сравнивают ячейки внутри одной команды.</p><?php render_hero_damage_matrix('Свет', $radiant_players, $dire_players, $heroes, 'damage', 'dealt'); render_hero_damage_matrix('Тьма', $dire_players, $radiant_players, $heroes, 'damage', 'dealt'); ?></div></details>
        <details class="insight-details"><summary><span><strong>От кого получали урон</strong><small>Игрок ← вражеские герои</small></span><span class="insight-expand" aria-hidden="true">+</span></summary><div class="insight-details-body"><p class="insight-scroll-hint">На узком экране таблицу можно прокручивать по горизонтали.</p><p class="insight-muted">Строка — получатель урона. Колонка — атакующий герой. Здесь нет урона от крипов, строений и других источников: поэтому сумма может быть меньше «Всего по реплею».</p><?php render_hero_damage_matrix('Свет', $radiant_players, $dire_players, $heroes, 'damage_taken', 'received'); render_hero_damage_matrix('Тьма', $dire_players, $radiant_players, $heroes, 'damage_taken', 'received'); ?></div></details>
        <details class="insight-details"><summary><span><strong>Урон по строениям и Рошану</strong><small>Башни по уровням, казармы, крепость и Рошан</small></span><span class="insight-expand" aria-hidden="true">+</span></summary><div class="insight-details-body"><p class="insight-scroll-hint">На узком экране таблицу можно прокручивать по горизонтали.</p><p class="insight-muted">Рошан показан отдельно и входит в итог этой разбивки. Это не тот же показатель, что «Строения» в сводке матча.</p><?php render_building_damage_matrix('Свет', $radiant_players, $heroes); render_building_damage_matrix('Тьма', $dire_players, $heroes); ?></div></details>
        <details id="damage-explained" class="insight-details"><summary><span><strong>Как читать показатели</strong><small>Что считается, а чего в текущих данных нет</small></span><span class="insight-expand" aria-hidden="true">+</span></summary><div class="insight-details-body insight-glossary">
            <div><h4>Всего по реплею</h4><p>Сумма событий полученного урона в реплее — от героев, крипов, строений и других источников. Это не объём предотвращённого урона. Сумма событий реплея может отличаться от колонки «Всего» в клиенте Dota: мы не выдаём её за точную копию клиентской метрики.</p></div>
            <div><h4>Уменьшено, %</h4><p>Референс — послематчевая таблица Dota. Текущий парсер не сохраняет достаточных данных для этого процента. Броня и сопротивление в конце матча не позволяют восстановить его достоверно: показатель не рассчитывается.</p></div>
            <div><h4>Нанесено героям</h4><p>Сумма урона по конкретным вражеским героям из карты damage. Иллюзии и призванные существа не включены в эти пять целей; значение может отличаться от общей метрики Dota.</p></div>
            <div><h4>Лечение и строения</h4><p>Показатели hero_healing и tower_damage из метаданных матча. Нулевое значение — 0; если поля нет, показываем «Нет данных».</p></div>
        </div></details>
    </div>
    <?php
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
