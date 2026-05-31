<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('log'), 'журнал событий', static function () use ($match): void {
    render_log_match_tab($match);
});
