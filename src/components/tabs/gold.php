<?php

declare(strict_types=1);

function render_gold_match_tab(array $players, array $heroes): void
{
    render_metric_match_tab($players, $heroes, [
        'total_gold' => 'Net worth',
        'gold_per_min' => 'GPM',
        'last_hits' => 'Last hits',
        'denies' => 'Denies',
    ]);
}
