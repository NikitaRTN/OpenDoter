<?php

declare(strict_types=1);

/**
 * Настоящие бенчмарки в стиле OpenDota: каждый игрок сравнивается с базой
 * (baseline) своего героя. OpenDota возвращает для каждого игрока поле
 * `benchmarks`, где у каждой метрики есть сырое значение `raw` и перцентиль
 * `pct` (0..1) относительно базы этого героя. Мы рисуем эти перцентили
 * полосками, чтобы было видно, насколько игрок выступил относительно базы
 * героя, а не просто кто лидер матча по абсолютному числу.
 */

function benchmark_metric_labels(): array
{
    return [
        'gold_per_min' => 'GPM',
        'xp_per_min' => 'XPM',
        'last_hits_per_min' => 'Добивания/мин',
        'hero_damage_per_min' => 'Урон по героям/мин',
        'hero_healing_per_min' => 'Лечение/мин',
        'tower_damage' => 'Урон по строениям',
    ];
}

function benchmark_percentile_class(float $pct): string
{
    if ($pct >= 0.8) {
        return 'is-elite';
    }
    if ($pct >= 0.6) {
        return 'is-good';
    }
    if ($pct >= 0.4) {
        return 'is-average';
    }
    if ($pct >= 0.2) {
        return 'is-poor';
    }

    return 'is-low';
}

function benchmark_format_raw(string $metric, mixed $raw): string
{
    $value = (float) $raw;

    if ($metric === 'tower_damage') {
        return format_stat((int) round($value));
    }

    if ($metric === 'gold_per_min' || $metric === 'xp_per_min') {
        return (string) (int) round($value);
    }

    // Метрики «в минуту» оставляем с одним знаком после запятой для читабельности.
    return number_format($value, 1, '.', '');
}

function match_has_benchmarks(array $players): bool
{
    foreach ($players as $player) {
        if (!empty($player['benchmarks']) && is_array($player['benchmarks'])) {
            return true;
        }
    }

    return false;
}

function render_benchmark_player_head(array $player, array $heroes): void
{
    $hero_img = get_hero_img((int) ($player['hero_id'] ?? 0), $heroes);
    $name = (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним');
    ?>
    <div class="benchmark-card-head">
        <div class="player-cell compact">
            <?php if ($hero_img): ?><img class="hero-img" src="<?php echo e($hero_img); ?>" alt=""><?php endif; ?>
            <div class="player-info">
                <?php if (!empty($player['account_id'])): ?>
                    <a class="player-name" href="<?php echo e(player_url($player['account_id'])); ?>"><?php echo e($name); ?></a>
                <?php else: ?>
                    <span class="player-name"><?php echo e($name); ?></span>
                <?php endif; ?>
                <span class="player-rank"><?php echo e(get_hero_name((int) ($player['hero_id'] ?? 0), $heroes)); ?> · <?php echo e(player_match_team_label($player)); ?></span>
            </div>
        </div>
    </div>
    <?php
}

function render_benchmarks_match_tab(array $players, array $heroes): void
{
    if (!match_has_benchmarks($players)) {
        render_match_tab_empty('Бенчмарки недоступны: для этого матча нет данных перцентилей (матч не распарсен).');

        return;
    }

    $metrics = benchmark_metric_labels();
    ?>
    <div class="benchmarks-grid">
        <?php foreach ($players as $player): ?>
            <?php $benchmarks = is_array($player['benchmarks'] ?? null) ? $player['benchmarks'] : []; ?>
            <div class="benchmark-card">
                <?php render_benchmark_player_head($player, $heroes); ?>
                <div class="benchmark-metrics">
                    <?php foreach ($metrics as $key => $label): ?>
                        <?php
                        $entry = is_array($benchmarks[$key] ?? null) ? $benchmarks[$key] : [];
                        $has_value = array_key_exists('pct', $entry);
                        $pct = $has_value ? max(0.0, min(1.0, (float) $entry['pct'])) : 0.0;
                        $pct_width = (int) round($pct * 100);
                        $pct_label = $has_value ? $pct_width . '%' : '—';
                        $raw_label = $has_value ? benchmark_format_raw($key, $entry['raw'] ?? 0) : '';
                        ?>
                        <div class="benchmark-row">
                            <div class="benchmark-label"><?php echo e($label); ?></div>
                            <div class="benchmark-bar" title="Перцентиль относительно базы героя">
                                <div class="benchmark-bar-fill <?php echo e($has_value ? benchmark_percentile_class($pct) : 'is-empty'); ?>" style="width: <?php echo e((string) $pct_width); ?>%;"></div>
                            </div>
                            <div class="benchmark-value">
                                <span class="benchmark-pct"><?php echo e($pct_label); ?></span>
                                <?php if ($raw_label !== ''): ?><span class="benchmark-raw"><?php echo e($raw_label); ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
