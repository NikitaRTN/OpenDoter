<?php

declare(strict_types=1);

function render_actions_match_tab(array $players, array $heroes): void
{
    $rows = [];
    foreach ($players as $player) {
        $actions = is_array($player['actions_per_min'] ?? null) ? array_sum($player['actions_per_min']) : 0;
        $rows[] = [$player, (int) $actions];
    }
    usort($rows, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
    ?>
    <table class="overview-table"><thead><tr><th>Игрок</th><th class="col-center">Actions score</th></tr></thead><tbody>
    <?php foreach ($rows as [$player, $actions]): ?><tr><?php render_player_match_cells($player, $heroes); ?><td class="col-center"><?php echo e($actions > 0 ? format_stat($actions) : '-'); ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php
}
