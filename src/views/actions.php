<?php

declare(strict_types=1);

$players = array_merge($radiant_players, $dire_players);
$duration = (int) ($match['duration'] ?? 0);
render_match_tab_section(match_tab_title('actions'), 'активность игроков', static function () use ($players, $heroes, $duration): void {
    render_actions_match_tab($players, $heroes, $duration);
});
