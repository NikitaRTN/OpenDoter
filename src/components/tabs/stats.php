<?php

declare(strict_types=1);

function render_stats_match_tab(array $players, array $heroes): void
{
    $table_id = 'stats-table';
    $center = ' style="text-align:center;"';
    $sortable = ' style="text-align:center;cursor:pointer;white-space:nowrap;" title="Нажмите для сортировки"';
    ?>
    <table class="overview-table sortable-table" id="<?php echo e($table_id); ?>">
        <thead>
            <tr>
                <th>Игрок</th>
                <th class="col-center" data-sortable="1" data-type="text"<?php echo $sortable; ?>>Команда</th>
                <th class="col-center" data-sortable="1" data-type="number"<?php echo $sortable; ?>>K/D/A</th>
                <th class="col-center" data-sortable="1" data-type="number"<?php echo $sortable; ?>>LH/DN</th>
                <th class="col-center" data-sortable="1" data-type="number"<?php echo $sortable; ?>>GPM/XPM</th>
                <th class="col-center" data-sortable="1" data-type="number"<?php echo $sortable; ?>>Уровень</th>
                <th class="col-center" data-sortable="1" data-type="number"<?php echo $sortable; ?>>Урон по героям</th>
                <th class="col-center" data-sortable="1" data-type="number"<?php echo $sortable; ?>>Лечение</th>
                <th class="col-center" data-sortable="1" data-type="number"<?php echo $sortable; ?>>Урон по строениям</th>
                <th class="col-center" data-sortable="1" data-type="number"<?php echo $sortable; ?>>Net worth</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($players as $player): ?>
            <?php
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
            ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>" data-sort="<?php echo e(player_match_team_label($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <td class="col-center" data-sort="<?php echo $kills; ?>"><?php echo e($kills . '/' . $deaths . '/' . $assists); ?></td>
                <td class="col-center" data-sort="<?php echo $last_hits; ?>"><?php echo e($last_hits . '/' . $denies); ?></td>
                <td class="col-center" data-sort="<?php echo $gpm; ?>"><?php echo e($gpm . '/' . $xpm); ?></td>
                <td class="col-center" data-sort="<?php echo $level; ?>"><?php echo e($level); ?></td>
                <td class="col-center" data-sort="<?php echo $hero_damage; ?>"><?php echo e(format_stat($hero_damage)); ?></td>
                <td class="col-center" data-sort="<?php echo $hero_healing; ?>"><?php echo e(format_stat($hero_healing)); ?></td>
                <td class="col-center" data-sort="<?php echo $tower_damage; ?>"><?php echo e(format_stat($tower_damage)); ?></td>
                <td class="col-center col-gold" data-sort="<?php echo $net_worth; ?>"><?php echo e(format_stat($net_worth)); ?></td>
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
