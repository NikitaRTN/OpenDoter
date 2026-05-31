<?php

declare(strict_types=1);

function render_damage_match_tab(array $players, array $heroes): void
{
    render_metric_match_tab($players, $heroes, [
        'hero_damage' => 'Урон по героям',
        'tower_damage' => 'Урон по строениям',
        'hero_healing' => 'Лечение',
    ]);
}
