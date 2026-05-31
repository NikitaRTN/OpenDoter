<?php

declare(strict_types=1);

function render_fantasy_match_tab(array $players, array $heroes): void
{
    usort($players, static fn (array $a, array $b): int => fantasy_match_score($b) <=> fantasy_match_score($a));
    ?>
    <table class="overview-table"><thead><tr><th>Игрок</th><th class="col-center">Fantasy score</th><th class="col-center">Основа</th></tr></thead><tbody>
    <?php foreach ($players as $player): ?><tr><?php render_player_match_cells($player, $heroes); ?><td class="col-center col-gold"><?php echo e((string) fantasy_match_score($player)); ?></td><td class="col-center"><?php echo e(($player['kills'] ?? 0) . '/' . ($player['deaths'] ?? 0) . '/' . ($player['assists'] ?? 0)); ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php
}
