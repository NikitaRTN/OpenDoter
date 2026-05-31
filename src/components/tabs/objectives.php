<?php

declare(strict_types=1);

function render_objectives_match_tab(array $match): void
{
    render_events_match_tab($match['objectives'] ?? [], 'Целей в данных матча нет.');
}
