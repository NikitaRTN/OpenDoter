<?php

declare(strict_types=1);

function render_teamfights_match_tab(mixed $teamfights): void
{
    if (!is_array($teamfights) || $teamfights === []) {
        render_match_tab_empty('Teamfight-данные недоступны для этого матча.');
        return;
    }
    ?>
    <table class="overview-table"><thead><tr><th class="col-center">Время</th><th class="col-center">Длительность</th><th>Кратко</th></tr></thead><tbody>
    <?php foreach ($teamfights as $fight): ?>
        <tr><td class="col-center"><?php echo e(format_match_tab_time((int) ($fight['start'] ?? 0))); ?></td><td class="col-center"><?php echo e(format_match_tab_time((int) (($fight['end'] ?? 0) - ($fight['start'] ?? 0)))); ?></td><td><?php echo e(count($fight['players'] ?? []) . ' игроков в событии'); ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
}
