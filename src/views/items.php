<?php

declare(strict_types=1);

$players = array_merge($radiant_players, $dire_players);
render_match_tab_section(match_tab_title('items'), 'предметы игроков', static function () use ($players, $heroes, $items_by_id): void {
    render_items_match_tab($players, $heroes, $items_by_id);
});
