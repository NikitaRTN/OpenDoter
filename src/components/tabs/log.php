<?php

declare(strict_types=1);

function render_log_match_tab(array $match, array $radiant_players, array $dire_players, array $heroes): void
{
    $events = array_merge(
        is_array($match['objectives'] ?? null) ? $match['objectives'] : [],
        is_array($match['teamfights'] ?? null) ? $match['teamfights'] : [],
        is_array($match['chat'] ?? null) ? $match['chat'] : [],
        is_array($match['pauses'] ?? null) ? $match['pauses'] : []
    );

    render_events_match_tab($events, 'Технический журнал событий недоступен.');
}
