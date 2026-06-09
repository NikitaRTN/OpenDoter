<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('teamfights'), 'командные сражения', static function () use ($match, $radiant_players, $dire_players, $heroes): void {
    render_teamfights_match_tab($match['teamfights'] ?? [], $radiant_players, $dire_players, $heroes);
});
