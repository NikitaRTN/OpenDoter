<?php

declare(strict_types=1);

$players = array_merge($radiant_players, $dire_players);
render_match_tab_section(match_tab_title('benchmarks'), 'лучшие показатели матча', static function () use ($players, $heroes): void {
    render_benchmarks_match_tab($players, $heroes);
});
