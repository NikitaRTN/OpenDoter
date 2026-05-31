<?php

declare(strict_types=1);

function render_benchmarks_match_tab(array $players, array $heroes): void
{
    $metrics = [
        'gold_per_min' => 'Лучший GPM',
        'xp_per_min' => 'Лучший XPM',
        'hero_damage' => 'Больше всего урона',
        'tower_damage' => 'Больше всего по строениям',
        'last_hits' => 'Больше всего CS',
        'kda' => 'Лучший KDA',
    ];
    ?>
    <table class="overview-table">
        <thead><tr><th>Бенчмарк</th><th>Игрок</th><th class="col-center">Значение</th></tr></thead>
        <tbody>
        <?php foreach ($metrics as $key => $label): ?>
            <?php
            $ranked = $players;
            usort($ranked, static function (array $a, array $b) use ($key): int {
                $a_value = $key === 'kda' ? player_match_kda($a) : (float) ($a[$key] ?? 0);
                $b_value = $key === 'kda' ? player_match_kda($b) : (float) ($b[$key] ?? 0);
                return $b_value <=> $a_value;
            });
            $top = $ranked[0] ?? [];
            ?>
            <tr>
                <td><?php echo e($label); ?></td>
                <?php render_player_match_cells($top, $heroes); ?>
                <td class="col-center col-gold"><?php echo e($key === 'kda' ? number_format(player_match_kda($top), 2) : format_stat($top[$key] ?? 0)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
