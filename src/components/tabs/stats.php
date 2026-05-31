<?php

declare(strict_types=1);

function render_stats_match_tab(array $players, array $heroes): void
{
    ?>
    <table class="overview-table">
        <thead><tr><th>Игрок</th><th class="col-center">Команда</th><th class="col-center">K/D/A</th><th class="col-center">LH/DN</th><th class="col-center">GPM/XPM</th><th class="col-center">Уровень</th><th class="col-center">Net worth</th></tr></thead>
        <tbody>
        <?php foreach ($players as $player): ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <td class="col-center"><?php echo e(($player['kills'] ?? 0) . '/' . ($player['deaths'] ?? 0) . '/' . ($player['assists'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e(($player['last_hits'] ?? 0) . '/' . ($player['denies'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e(($player['gold_per_min'] ?? 0) . '/' . ($player['xp_per_min'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e($player['level'] ?? 0); ?></td>
                <td class="col-center col-gold"><?php echo e(format_stat($player['total_gold'] ?? 0)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
