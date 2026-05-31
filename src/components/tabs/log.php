<?php

declare(strict_types=1);

function render_log_match_tab(array $match): void
{
    render_events_match_tab(
        array_merge($match['objectives'] ?? [], $match['chat'] ?? []),
        'Журнал событий недоступен.'
    );
}
