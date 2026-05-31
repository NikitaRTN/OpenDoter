<?php

declare(strict_types=1);

function render_graphs_match_tab(array $players, array $heroes): void
{
    render_metric_match_tab($players, $heroes, [
        'total_gold' => 'Net worth',
        'hero_damage' => 'Урон',
        'xp_per_min' => 'XPM',
    ]);
}
