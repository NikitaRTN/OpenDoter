<?php

declare(strict_types=1);

render_team_overview(
    'Силы Света',
    'radiant-title',
    $radiant_players,
    !empty($match['radiant_win']),
    $heroes,
    $items_by_id
);

render_draft('Драфт Сил Света (Radiant)', 'radiant-title', $radiant_picks, $radiant_bans, $heroes);

render_team_overview(
    'Силы Тьмы',
    'dire-title',
    $dire_players,
    empty($match['radiant_win']),
    $heroes,
    $items_by_id
);

render_draft('Драфт Сил Тьмы (Dire)', 'dire-title', $dire_picks, $dire_bans, $heroes);
