<?php

declare(strict_types=1);

render_match_tab_section(match_tab_title('objectives'), 'события и цели', static function () use ($match): void {
    render_objectives_match_tab($match);
});
