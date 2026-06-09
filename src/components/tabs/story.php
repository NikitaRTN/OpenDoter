<?php

declare(strict_types=1);

function render_story_match_tab(array $match, array $radiant_players, array $dire_players, array $heroes): void
{
    $lookup = match_build_slot_lookup($radiant_players, $dire_players);
    $events = array_merge(
        is_array($match['objectives'] ?? null) ? $match['objectives'] : [],
        is_array($match['teamfights'] ?? null) ? $match['teamfights'] : []
    );
    render_timeline_events_tab($events, $lookup, $heroes, 'Недостаточно ключевых событий, чтобы собрать историю матча.');
}
