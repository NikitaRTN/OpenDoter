<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('compare'), 'сравнение героев по показателям', static function () use ($match, $radiant_players, $dire_players, $heroes): void {
    render_compare_match_tab($radiant_players, $dire_players, $heroes, $match);
});
