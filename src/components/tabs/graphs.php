<?php

declare(strict_types=1);

function render_graphs_match_tab(array $players, array $heroes): void
{
    $time_metrics = [
        'gold_t' => ['title' => 'Net worth по минутам', 'label' => 'NW', 'color' => '#f1c40f'],
        'xp_t' => ['title' => 'Опыт по минутам', 'label' => 'XP', 'color' => '#3498db'],
        'lh_t' => ['title' => 'Ластхиты по минутам', 'label' => 'LH', 'color' => '#2ecc71'],
        'dn_t' => ['title' => 'Денаи по минутам', 'label' => 'DN', 'color' => '#e67e22'],
    ];

    $has_time_series = false;
    foreach ($players as $player) {
        foreach ($time_metrics as $key => $_meta) {
            if (isset($player[$key]) && is_array($player[$key]) && count($player[$key]) > 1) {
                $has_time_series = true;
                break 2;
            }
        }
    }

    if (!$has_time_series) {
        render_match_tab_empty('Поминутные графики недоступны: матч не распарсен или API не отдал серии gold_t/xp_t/lh_t/dn_t. Ниже показаны итоговые диаграммы.');
    }

    if ($has_time_series) {
        ?>
        <div class="graphs-grid">
            <?php foreach ($time_metrics as $key => $meta): ?>
                <?php if (graphs_has_series($players, $key)): ?>
                    <div class="graph-card">
                        <div class="graph-title"><?php echo e($meta['title']); ?></div>
                        <?php render_players_line_graph($players, $heroes, $key); ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    ?>
    <div class="graphs-grid">
        <?php render_players_bar_graph($players, $heroes, 'hero_damage', 'Урон по героям'); ?>
        <?php render_players_bar_graph($players, $heroes, 'tower_damage', 'Урон по строениям'); ?>
        <?php render_players_bar_graph($players, $heroes, 'gold_per_min', 'GPM'); ?>
        <?php render_players_bar_graph($players, $heroes, 'xp_per_min', 'XPM'); ?>
    </div>
    <?php
}

function graphs_has_series(array $players, string $key): bool
{
    foreach ($players as $player) {
        if (isset($player[$key]) && is_array($player[$key]) && count($player[$key]) > 1) {
            return true;
        }
    }

    return false;
}

function graph_player_color(int $idx, array $player): string
{
    $radiant = player_match_team_label($player) === 'Свет';
    $radiant_palette = ['#2ecc71', '#58d68d', '#27ae60', '#82e0aa', '#1abc9c'];
    $dire_palette = ['#e74c3c', '#ec7063', '#c0392b', '#f1948a', '#e84393'];
    $palette = $radiant ? $radiant_palette : $dire_palette;

    return $palette[$idx % count($palette)];
}

function render_players_line_graph(array $players, array $heroes, string $series_key): void
{
    static $chart_counter = 0;

    $series = [];
    $max_len = 1;
    $max_val = 1;
    $idx = 0;
    foreach ($players as $player) {
        $values = isset($player[$series_key]) && is_array($player[$series_key]) ? array_values(array_map('intval', $player[$series_key])) : [];
        if (count($values) <= 1) {
            $idx++;
            continue;
        }
        $hero_id = (int) ($player['hero_id'] ?? 0);
        $hero = get_hero_name($hero_id, $heroes);
        $name = (string) ($player['personaname'] ?? $player['name'] ?? $hero);
        $img = get_hero_img($hero_id, $heroes);
        $color = graph_player_color($idx, $player);

        $max_len = max($max_len, count($values));
        $max_val = max($max_val, max($values));
        $series[] = [
            'player' => $player,
            'values' => $values,
            'color' => $color,
            'hero' => $hero,
            'name' => $name,
            'img' => $img,
        ];
        $idx++;
    }

    if ($series === []) {
        render_match_tab_empty('Нет данных для графика.');
        return;
    }

    $width = 1100;
    $height = 340;
    $left = 68;
    $right = 24;
    $top = 20;
    $bottom = 42;
    $plot_w = $width - $left - $right;
    $plot_h = $height - $top - $bottom;
    $x = static fn (int $i): float => $left + ($max_len <= 1 ? 0 : ($i / ($max_len - 1)) * $plot_w);
    $y = static fn (int $v): float => $top + $plot_h - ($v / $max_val) * $plot_h;
    $chart_id = 'graph-line-' . (++$chart_counter);
    $chart_data = [
        'width' => $width,
        'height' => $height,
        'left' => $left,
        'right' => $right,
        'top' => $top,
        'bottom' => $bottom,
        'plotW' => $plot_w,
        'plotH' => $plot_h,
        'maxLen' => $max_len,
        'maxVal' => $max_val,
        'series' => array_map(static fn (array $line): array => [
            'hero' => $line['hero'],
            'name' => $line['name'],
            'img' => $line['img'],
            'color' => $line['color'],
            'values' => $line['values'],
        ], $series),
    ];
    ?>
    <div class="graph-line-wrap" data-graph-chart="<?php echo e($chart_id); ?>">
        <svg class="graph-svg" viewBox="0 0 <?php echo $width; ?> <?php echo $height; ?>" preserveAspectRatio="none" role="img">
            <?php for ($g = 0; $g <= 4; $g++): ?>
                <?php $val = (int) round($max_val * $g / 4); $yy = $y($val); ?>
                <line x1="<?php echo $left; ?>" y1="<?php echo $yy; ?>" x2="<?php echo $width - $right; ?>" y2="<?php echo $yy; ?>" class="graph-grid-line" />
                <text x="<?php echo $left - 8; ?>" y="<?php echo $yy + 4; ?>" text-anchor="end" class="graph-axis-label"><?php echo e(format_stat($val)); ?></text>
            <?php endfor; ?>
            <?php for ($minute = 0; $minute < $max_len; $minute += 5): ?>
                <text x="<?php echo $x($minute); ?>" y="<?php echo $height - 8; ?>" text-anchor="middle" class="graph-axis-label"><?php echo $minute; ?></text>
            <?php endfor; ?>
            <?php foreach ($series as $line): ?>
                <?php
                $d = '';
                foreach ($line['values'] as $i => $value) {
                    $d .= ($i === 0 ? 'M' : 'L') . number_format($x((int) $i), 1, '.', '') . ' ' . number_format($y((int) $value), 1, '.', '') . ' ';
                }
                ?>
                <path d="<?php echo e($d); ?>" fill="none" stroke="<?php echo e($line['color']); ?>" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"></path>
            <?php endforeach; ?>
            <line class="graph-crosshair" x1="0" y1="<?php echo $top; ?>" x2="0" y2="<?php echo $height - $bottom; ?>" hidden />
            <circle class="graph-hover-dot" r="5" hidden />
            <rect class="graph-hover-layer" x="<?php echo $left; ?>" y="<?php echo $top; ?>" width="<?php echo $plot_w; ?>" height="<?php echo $plot_h; ?>"></rect>
        </svg>
        <script type="application/json" id="<?php echo e($chart_id); ?>-data"><?php echo json_encode($chart_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
        <div class="graph-tooltip" role="tooltip"></div>
    </div>
    <div class="graph-legend">
        <?php foreach ($series as $line): ?>
            <span class="graph-legend-item <?php echo e(player_match_team_class($line['player'])); ?>"><i style="background: <?php echo e($line['color']); ?>"></i><?php echo e($line['hero']); ?></span>
        <?php endforeach; ?>
    </div>
    <?php render_graph_tooltip_script(); ?>
    <?php
}

function render_graph_tooltip_script(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;
    ?>
    <script>
    (function () {
        if (window.__opendoterGraphTooltipBound) { return; }
        window.__opendoterGraphTooltipBound = true;

        function esc(value) {
            return String(value || '').replace(/[&<>"']/g, function (ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
            });
        }

        function fullNum(value) {
            var number = Number(value) || 0;
            return number.toLocaleString('ru-RU');
        }

        function readChartData(wrap) {
            if (wrap.__graphData) { return wrap.__graphData; }
            var id = wrap.getAttribute('data-graph-chart');
            var node = id ? document.getElementById(id + '-data') : null;
            if (!node) { return null; }
            try {
                wrap.__graphData = JSON.parse(node.textContent || '{}');
                return wrap.__graphData;
            } catch (error) {
                return null;
            }
        }

        function pointY(data, value) {
            return data.top + data.plotH - ((Number(value) || 0) / Math.max(1, data.maxVal)) * data.plotH;
        }

        function pointX(data, minute) {
            return data.left + (data.maxLen <= 1 ? 0 : (minute / (data.maxLen - 1)) * data.plotW);
        }

        function nearestPoint(data, svg, event) {
            var rect = svg.getBoundingClientRect();
            var sx = (event.clientX - rect.left) / Math.max(1, rect.width) * data.width;
            var sy = (event.clientY - rect.top) / Math.max(1, rect.height) * data.height;
            var minute = Math.round((sx - data.left) / Math.max(1, data.plotW) * (data.maxLen - 1));
            minute = Math.max(0, Math.min(data.maxLen - 1, minute));

            var best = null;
            data.series.forEach(function (line) {
                if (!Array.isArray(line.values) || !line.values.length) { return; }
                var index = Math.max(0, Math.min(line.values.length - 1, minute));
                var value = Number(line.values[index]) || 0;
                var y = pointY(data, value);
                var distance = Math.abs(y - sy);
                if (!best || distance < best.distance) {
                    best = { line: line, minute: index, value: value, x: pointX(data, index), y: y, distance: distance };
                }
            });
            return best;
        }

        function showTooltip(wrap, svg, data, point, event) {
            var tooltip = wrap.querySelector('.graph-tooltip');
            var crosshair = svg.querySelector('.graph-crosshair');
            var dot = svg.querySelector('.graph-hover-dot');
            if (!tooltip || !point) { return; }

            tooltip.innerHTML = '<div class="graph-tooltip-head">' +
                (point.line.img ? '<img src="' + esc(point.line.img) + '" alt="">' : '') +
                '<div><strong>' + esc(point.line.hero) + '</strong><span>' + esc(point.line.name) + '</span></div>' +
                '</div>' +
                '<div class="graph-tooltip-grid"><span>Минута</span><b>' + esc(point.minute) + ':00</b><span>Значение</span><b>' + esc(fullNum(point.value)) + '</b></div>';
            tooltip.style.display = 'block';

            if (crosshair) {
                crosshair.setAttribute('x1', point.x);
                crosshair.setAttribute('x2', point.x);
                crosshair.hidden = false;
            }
            if (dot) {
                dot.setAttribute('cx', point.x);
                dot.setAttribute('cy', point.y);
                dot.setAttribute('fill', point.line.color || '#fff');
                dot.hidden = false;
            }

            var rect = wrap.getBoundingClientRect();
            var left = Math.min(rect.width - tooltip.offsetWidth - 8, Math.max(8, event.clientX - rect.left + 12));
            var top = Math.min(rect.height - tooltip.offsetHeight - 8, Math.max(8, event.clientY - rect.top + 12));
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
        }

        function hideTooltip(wrap) {
            var tooltip = wrap.querySelector('.graph-tooltip');
            var crosshair = wrap.querySelector('.graph-crosshair');
            var dot = wrap.querySelector('.graph-hover-dot');
            if (tooltip) { tooltip.style.display = 'none'; }
            if (crosshair) { crosshair.hidden = true; }
            if (dot) { dot.hidden = true; }
        }

        document.addEventListener('mousemove', function (event) {
            var layer = event.target.closest && event.target.closest('.graph-hover-layer');
            if (!layer) { return; }
            var wrap = layer.closest('.graph-line-wrap');
            var svg = layer.closest('svg');
            var data = wrap && readChartData(wrap);
            if (!wrap || !svg || !data) { return; }
            showTooltip(wrap, svg, data, nearestPoint(data, svg, event), event);
        });

        document.addEventListener('mouseleave', function (event) {
            var wrap = event.target.closest && event.target.closest('.graph-line-wrap');
            if (wrap) { hideTooltip(wrap); }
        }, true);
    })();
    </script>
    <?php
}

function render_players_bar_graph(array $players, array $heroes, string $metric, string $title): void
{
    $rows = [];
    $max = 1;
    foreach ($players as $player) {
        $value = (int) ($player[$metric] ?? 0);
        $max = max($max, $value);
        $rows[] = ['player' => $player, 'value' => $value];
    }
    usort($rows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);
    ?>
    <div class="graph-card">
        <div class="graph-title"><?php echo e($title); ?></div>
        <div class="graph-bars">
            <?php foreach ($rows as $idx => $row): ?>
                <?php
                $player = $row['player'];
                $value = (int) $row['value'];
                $pct = max(2, (int) round($value / $max * 100));
                $hero = get_hero_name((int) ($player['hero_id'] ?? 0), $heroes);
                $img = get_hero_img((int) ($player['hero_id'] ?? 0), $heroes);
                $color = graph_player_color($idx, $player);
                ?>
                <div class="graph-bar-row" title="<?php echo e($hero . ': ' . number_format($value)); ?>">
                    <div class="graph-bar-name <?php echo e(player_match_team_class($player)); ?>">
                        <?php if ($img): ?><img src="<?php echo e($img); ?>" alt=""><?php endif; ?>
                        <span><?php echo e($hero); ?></span>
                    </div>
                    <div class="graph-bar-track"><div class="graph-bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo e($color); ?>"></div></div>
                    <div class="graph-bar-value"><?php echo e(format_stat($value)); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
