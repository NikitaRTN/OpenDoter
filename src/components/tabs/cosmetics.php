<?php

declare(strict_types=1);

/**
 * Вкладка «Снаряжение». Раньше по ошибке рисовалась таблица ПОКАЗАТЕЛЕЙ
 * (render_stats_match_tab) вместо косметики. Теперь показываем реальные
 * косметические предметы каждого игрока.
 */
function render_cosmetics_match_tab(array $players, array $heroes): void
{
    $rows = [];
    foreach ($players as $player) {
        $cosmetics = is_array($player['cosmetics'] ?? null) ? $player['cosmetics'] : [];
        if ($cosmetics !== []) {
            $rows[] = [$player, $cosmetics];
        }
    }

    if ($rows === []) {
        render_match_tab_empty('Данные о косметических предметах не пришли из API.');
        return;
    }

    usort($rows, static fn (array $a, array $b): int => count($b[1]) <=> count($a[1]));
    ?>
    <table class="overview-table">
        <thead><tr><th>Игрок</th><th class="col-center">Предметов</th><th>Снаряжение</th></tr></thead>
        <tbody>
        <?php foreach ($rows as [$player, $cosmetics]): ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center"><?php echo e((string) count($cosmetics)); ?></td>
                <td><?php echo e(cosmetics_names_summary($cosmetics)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function cosmetics_names_summary(array $cosmetics): string
{
    $names = [];
    foreach ($cosmetics as $item) {
        if (is_array($item)) {
            $name = (string) ($item['name'] ?? $item['name_english_loc'] ?? $item['item_id'] ?? '');
        } else {
            $name = (string) $item;
        }
        if ($name !== '') {
            $names[] = $name;
        }
    }

    if ($names === []) {
        return '—';
    }

    $shown = array_slice($names, 0, 12);
    return implode(', ', $shown) . (count($names) > 12 ? ' …' : '');
}
