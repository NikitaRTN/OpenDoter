<?php

declare(strict_types=1);

$players = array_merge($radiant_players, $dire_players);
render_gold_page($match, $players, $heroes);
