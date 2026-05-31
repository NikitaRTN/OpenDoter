<?php

declare(strict_types=1);

function render_story_match_tab(array $match): void
{
    render_events_match_tab(
        array_merge($match['objectives'] ?? [], $match['teamfights'] ?? [], $match['chat'] ?? []),
        'Недостаточно событий, чтобы собрать историю матча.'
    );
}
