<?php

declare(strict_types=1);

function render_cosmetics_match_tab(array $players, array $heroes): void
{
    foreach ($players as $player) {
        if (!empty($player['cosmetics'])) {
            render_stats_match_tab($players, $heroes);
            return;
        }
    }
    render_match_tab_empty('Данные о косметических предметах не пришли из API.');
}
