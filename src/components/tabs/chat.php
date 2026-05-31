<?php

declare(strict_types=1);

function render_chat_match_tab(array $match): void
{
    render_events_match_tab($match['chat'] ?? [], 'Чат матча недоступен.');
}
