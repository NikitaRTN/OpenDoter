<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('story'), 'хронология матча', static function () use ($match): void {
    render_story_match_tab($match);
});
