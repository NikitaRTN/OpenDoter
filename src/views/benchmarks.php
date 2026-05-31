<?php

declare(strict_types=1);

$players = array_merge($radiant_players, $dire_players);

render_match_tab_section(
    match_tab_title('benchmarks'),
    'перцентили против базы героя',
    static fn () => render_benchmarks_match_tab($players, $heroes)
);
