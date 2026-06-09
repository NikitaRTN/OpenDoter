<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('story'), 'хронология матча', static function () use ($match, $radiant_players, $dire_players, $heroes): void {
    render_story_match_tab($match, $radiant_players, $dire_players, $heroes);
});
