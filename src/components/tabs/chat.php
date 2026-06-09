<?php

declare(strict_types=1);

function render_chat_match_tab(array $match, array $radiant_players, array $dire_players, array $heroes): void
{
    $lookup = match_build_slot_lookup($radiant_players, $dire_players);
    $chat = is_array($match['chat'] ?? null) ? $match['chat'] : [];
    render_chat_messages_tab($chat, $lookup, $heroes);
}
