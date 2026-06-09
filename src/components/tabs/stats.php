<?php

declare(strict_types=1);

function render_stats_match_tab(array $players, array $heroes): void
{
    $table_id = 'stats-table';
    $team_kills = ['Свет' => 0, 'Тьма' => 0];
    foreach ($players as $player) {
        $team_kills[player_match_team_label($player)] += (int) ($player['kills'] ?? 0);
    }

    $rows = [];
    foreach ($players as $player) {
        $kills = (int) ($player['kills'] ?? 0);
        $deaths = (int) ($player['deaths'] ?? 0);
        $assists = (int) ($player['assists'] ?? 0);
        $last_hits = (int) ($player['last_hits'] ?? 0);
        $denies = (int) ($player['denies'] ?? 0);
        $gpm = (int) ($player['gold_per_min'] ?? 0);
        $xpm = (int) ($player['xp_per_min'] ?? 0);
        $level = (int) ($player['level'] ?? 0);
        $hero_damage = (int) ($player['hero_damage'] ?? 0);
        $hero_healing = (int) ($player['hero_healing'] ?? 0);
        $tower_damage = (int) ($player['tower_damage'] ?? 0);
        $net_worth = (int) ($player['net_worth'] ?? $player['total_gold'] ?? 0);
        $team = player_match_team_label($player);
        $kp = $team_kills[$team] > 0 ? (($kills + $assists) / $team_kills[$team]) * 100 : 0;
        $kda = ($kills + $assists) / max(1, $deaths);
        $fight_score = $hero_damage + $hero_healing + $tower_damage;
        $farm_score = $gpm + $xpm + ($last_hits * 2) + $denies;
        $rows[] = compact('player', 'kills', 'deaths', 'assists', 'last_hits', 'denies', 'gpm', 'xpm', 'level', 'hero_damage', 'hero_healing', 'tower_damage', 'net_worth', 'team', 'kp', 'kda', 'fight_score', 'farm_score');
    }

    usort($rows, static fn (array $a, array $b): int => $b['fight_score'] <=> $a['fight_score']);
    $best_damage = max(1, ...array_map(static fn (array $row): int => $row['hero_damage'], $rows));
    $best_farm = max(1, ...array_map(static fn (array $row): int => $row['farm_score'], $rows));
    ?>
    <table class="overview-table sortable-table advanced-stats-table" id="<?php echo e($table_id); ?>">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center" data-sortable="1" data-type="text">Команда</th>
                <th class="col-center" data-sortable="1" data-type="number" title="Убийства / смерти / помощи">K/D/A</th>
                <th class="col-center" data-sortable="1" data-type="number" title="(kills + assists) / deaths">KDA</th>
                <th class="col-center" data-sortable="1" data-type="number" title="Участие в убийствах команды">KP</th>
                <th class="col-center" data-sortable="1" data-type="number">LH/DN</th>
                <th class="col-center" data-sortable="1" data-type="number">GPM/XPM</th>
                <th class="col-center" data-sortable="1" data-type="number">Ур.</th>
                <th data-sortable="1" data-type="number">Боевой вклад</th>
                <th data-sortable="1" data-type="number">Фарм-индекс</th>
                <th class="col-center" data-sortable="1" data-type="number">Net worth</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $player = $row['player'];
            $damage_pct = max(2, (int) round($row['hero_damage'] / $best_damage * 100));
            $farm_pct = max(2, (int) round($row['farm_score'] / $best_farm * 100));
            ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>" data-sort="<?php echo e($row['team']); ?>"><?php echo e($row['team']); ?></td>
                <td class="col-center" data-sort="<?php echo $row['kills']; ?>"><?php echo e($row['kills'] . '/' . $row['deaths'] . '/' . $row['assists']); ?></td>
                <td class="col-center" data-sort="<?php echo e(number_format($row['kda'], 2, '.', '')); ?>"><?php echo e(number_format($row['kda'], 2, '.', '')); ?></td>
                <td class="col-center" data-sort="<?php echo e(number_format($row['kp'], 1, '.', '')); ?>"><?php echo e(number_format($row['kp'], 0)); ?>%</td>
                <td class="col-center" data-sort="<?php echo $row['last_hits']; ?>"><?php echo e($row['last_hits'] . '/' . $row['denies']); ?></td>
                <td class="col-center" data-sort="<?php echo $row['gpm']; ?>"><?php echo e($row['gpm'] . '/' . $row['xpm']); ?></td>
                <td class="col-center" data-sort="<?php echo $row['level']; ?>"><?php echo e($row['level']); ?></td>
                <td data-sort="<?php echo $row['fight_score']; ?>">
                    <div class="stats-bar-line" title="Урон: <?php echo e(number_format($row['hero_damage'])); ?> · лечение: <?php echo e(number_format($row['hero_healing'])); ?> · строения: <?php echo e(number_format($row['tower_damage'])); ?>">
                        <span><?php echo e(format_stat($row['hero_damage'])); ?></span>
                        <i style="width: <?php echo $damage_pct; ?>%"></i>
                    </div>
                </td>
                <td data-sort="<?php echo $row['farm_score']; ?>">
                    <div class="stats-bar-line farm" title="GPM <?php echo e($row['gpm']); ?> · XPM <?php echo e($row['xpm']); ?> · LH <?php echo e($row['last_hits']); ?> · DN <?php echo e($row['denies']); ?>">
                        <span><?php echo e((string) $row['farm_score']); ?></span>
                        <i style="width: <?php echo $farm_pct; ?>%"></i>
                    </div>
                </td>
                <td class="col-center col-gold" data-sort="<?php echo $row['net_worth']; ?>"><?php echo e(format_stat($row['net_worth'])); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php render_stats_sort_script($table_id); ?>
    <?php
}

function render_stats_sort_script(string $table_id): void
{
    ?>
    <script>
    (function () {
        var table = document.getElementById(<?php echo json_encode($table_id, JSON_UNESCAPED_UNICODE); ?>);
        if (!table) { return; }
        var tbody = table.querySelector('tbody');
        if (!tbody) { return; }
        var headers = table.querySelectorAll('thead th[data-sortable]');
        Array.prototype.forEach.call(headers, function (th) {
            th.style.cursor = 'pointer';
            th.title = (th.title ? th.title + ' · ' : '') + 'Нажмите для сортировки';
            th.addEventListener('click', function () {
                var colIndex = th.cellIndex;
                var type = th.getAttribute('data-type') || 'text';
                var asc = th.getAttribute('data-sort-dir') !== 'asc';
                Array.prototype.forEach.call(headers, function (h) {
                    if (h !== th) { h.removeAttribute('data-sort-dir'); }
                });
                th.setAttribute('data-sort-dir', asc ? 'asc' : 'desc');
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                rows.sort(function (a, b) {
                    var ca = a.cells[colIndex];
                    var cb = b.cells[colIndex];
                    var va = ca ? (ca.getAttribute('data-sort') !== null ? ca.getAttribute('data-sort') : ca.textContent) : '';
                    var vb = cb ? (cb.getAttribute('data-sort') !== null ? cb.getAttribute('data-sort') : cb.textContent) : '';
                    if (type === 'number') {
                        var na = parseFloat(va);
                        var nb = parseFloat(vb);
                        if (isNaN(na)) { na = 0; }
                        if (isNaN(nb)) { nb = 0; }
                        return asc ? na - nb : nb - na;
                    }
                    va = String(va).toLowerCase();
                    vb = String(vb).toLowerCase();
                    if (va < vb) { return asc ? -1 : 1; }
                    if (va > vb) { return asc ? 1 : -1; }
                    return 0;
                });
                rows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    })();
    </script>
    <?php
}
