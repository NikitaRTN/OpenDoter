<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('chat'), 'сообщения матча', static function () use ($match, $radiant_players, $dire_players, $heroes): void {
    render_chat_match_tab($match, $radiant_players, $dire_players, $heroes);
});
