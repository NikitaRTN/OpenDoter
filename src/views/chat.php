<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('chat'), 'сообщения матча', static function () use ($match): void {
    render_chat_match_tab($match);
});
