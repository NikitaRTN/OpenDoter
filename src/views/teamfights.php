<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('teamfights'), 'командные сражения', static function () use ($match, $match_id, $radiant_players, $dire_players, $heroes, $abilities, $items): void {
    render_teamfights_match_tab($match['teamfights'] ?? [], $radiant_players, $dire_players, $heroes, ['abilities' => $abilities ?? [], 'items' => $items ?? [], 'match_id' => (string) $match_id]);
});
