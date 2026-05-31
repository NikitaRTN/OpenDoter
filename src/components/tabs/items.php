<?php

declare(strict_types=1);

function render_items_match_tab(array $players, array $heroes, array $items_by_id): void
{
    ?>
    <table class="overview-table">
        <thead><tr><th>Игрок</th><th class="col-center">Команда</th><th>Предметы</th><th class="col-center">Aghanim</th></tr></thead>
        <tbody>
        <?php foreach ($players as $player): ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <td><?php render_items_cell($player, $items_by_id); ?></td>
                <td class="col-center"><?php echo !empty($player['aghanims_scepter']) ? 'Scepter ' : ''; ?><?php echo !empty($player['aghanims_shard']) ? 'Shard' : ''; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
