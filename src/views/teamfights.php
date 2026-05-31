<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('teamfights'), 'командные сражения', static function () use ($match): void {
    render_teamfights_match_tab($match['teamfights'] ?? []);
});
