<?php

declare(strict_types=1);

$players = array_merge($radiant_players, $dire_players);
$tab_abilities = $abilities ?? [];
$tab_ability_ids = $ability_ids ?? [];
render_match_tab_section(match_tab_title('abilities'), 'порядок прокачки', static function () use ($players, $heroes, $tab_abilities, $tab_ability_ids): void {
    render_abilities_match_tab($players, $heroes, $tab_abilities, $tab_ability_ids);
});
