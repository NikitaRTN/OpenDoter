<?php

declare(strict_types=1);

$players = array_merge($radiant_players, $dire_players);
render_match_tab_section(match_tab_title('graphs'), 'ключевые метрики', static function () use ($players, $heroes): void {
    render_graphs_match_tab($players, $heroes);
});
