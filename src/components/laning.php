<?php
declare(strict_types=1);

function render_laning_page(
    array $match,
    array $radiant_players,
    array $dire_players,
    array $heroes
): void {
    $laning = build_laning_analysis($radiant_players, $dire_players, $heroes);
    $match_id = (string) ($match['match_id'] ?? '');
    $has_data = !empty($laning['lanes']);
    $control = $laning['control'];
    ?>
    <?php render_detailed_stats_gate($match_id, 'статистики лейнинга', $has_data); ?>
    <div data-stats-content <?php echo $has_data ? '' : 'hidden'; ?>>
        <section class="laning-layout" data-laning-root>
            <div class="laning-header">
                <div class="laning-header-text">
                    <div class="laning-title">Лейнинг · первые 10 минут</div>
                    <div class="laning-subtitle">Линии определяются по позициям на карте и слотам. CS, золото и опыт — накопление к 10-й минуте.</div>
                </div>
                <div class="laning-scoreboard">
                    <span class="laning-score radiant">Свет <strong><?php echo e((string) $laning['lanes_won']['radiant']); ?></strong></span>
                    <span class="laning-score-vs">линий</span>
                    <span class="laning-score dire"><strong><?php echo e((string) $laning['lanes_won']['dire']); ?></strong> Тьма</span>
                </div>
            </div>

            <div class="lane-control-bar" title="Совокупный контроль линий по очкам">
                <div class="lane-control-fill radiant" style="width: <?php echo e((string) $control['radiant']); ?>%"><span><?php echo e((string) $control['radiant']); ?>%</span></div>
                <div class="lane-control-fill dire" style="width: <?php echo e((string) $control['dire']); ?>%"><span><?php echo e((string) $control['dire']); ?>%</span></div>
            </div>

            <?php foreach ($laning['lanes'] as $lane): ?>
                <?php render_laning_lane_card($lane); ?>
            <?php endforeach; ?>
        </section>
    </div>
    <?php
}

function render_laning_lane_card(array $lane): void
{
    $r_pct = (int) round((float) ($lane['radiant_pct'] ?? 50));
    $r_pct = max(0, min(100, $r_pct));
    $d_pct = 100 - $r_pct;

    $r_series = laning_side_cs_series($lane['radiant']);
    $d_series = laning_side_cs_series($lane['dire']);
    $chart = laning_lane_chart($r_series, $d_series);
    ?>
    <div class="laning-lane-card <?php echo e($lane['winner']); ?>">
        <div class="lane-header">
            <span class="lane-name"><?php echo e($lane['name']); ?></span>
            <span class="lane-winner <?php echo e($lane['winner']); ?>"><?php echo e($lane['winner_label']); ?></span>
        </div>

        <div class="lane-control-mini" title="Контроль этой линии">
            <div class="lane-control-mini-fill radiant" style="width: <?php echo e((string) $r_pct); ?>%"></div>
            <div class="lane-control-mini-fill dire" style="width: <?php echo e((string) $d_pct); ?>%"></div>
        </div>

        <div class="lane-matchup">
            <div class="lane-side radiant">
                <?php if (empty($lane['radiant'])): ?>
                    <div class="lane-empty">Нет данных</div>
                <?php else: ?>
                    <?php foreach ($lane['radiant'] as $p): ?>
                        <?php render_laning_player($p); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="lane-vs">VS</div>
            <div class="lane-side dire">
                <?php if (empty($lane['dire'])): ?>
                    <div class="lane-empty">Нет данных</div>
                <?php else: ?>
                    <?php foreach ($lane['dire'] as $p): ?>
                        <?php render_laning_player($p); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($chart !== ''): ?>
            <div class="lane-chart">
                <div class="lane-chart-head">
                    <span class="lane-chart-title">Крипы по времени (ластхиты 0–10 мин)</span>
                    <span class="lane-chart-legend"><i class="dot radiant"></i>Свет<i class="dot dire"></i>Тьма</span>
                </div>
                <?php echo $chart; ?>
                <div class="lane-chart-axis"><span>0</span><span>2</span><span>4</span><span>6</span><span>8</span><span>10</span></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function render_laning_player(array $p): void
{
    $adv = (int) $p['advantage'];
    $adv_class = $adv > 0 ? 'positive' : ($adv < 0 ? 'negative' : 'neutral');
    $color = ($p['team'] ?? '') === 'radiant' ? '#2ecc71' : '#e74c3c';
    $spark = laning_sparkline($p['lh_series'] ?? [], $color);
    ?>
    <div class="lane-player">
        <div class="lane-player-top">
            <?php if ($p['hero_img']): ?>
                <img class="hero-img" src="<?php echo e($p['hero_img']); ?>" alt="">
            <?php endif; ?>
            <div class="lane-player-id">
                <?php if (!empty($p['account_id'])): ?>
                    <a class="player-name" href="<?php echo e(player_url($p['account_id'])); ?>"><?php echo e($p['name']); ?></a>
                <?php else: ?>
                    <span class="player-name"><?php echo e($p['name']); ?></span>
                <?php endif; ?>
                <span class="lane-player-kda">K/D <?php echo e($p['kills'] . '/' . $p['deaths']); ?> · Ур <?php echo e((string) $p['level']); ?></span>
            </div>
            <?php if ($adv !== 0): ?>
                <span class="advantage <?php echo e($adv_class); ?>"><?php echo $adv > 0 ? '+' : ''; ?><?php echo e((string) $adv); ?>%</span>
            <?php endif; ?>
        </div>

        <div class="lane-player-stats">
            <span class="stat-chip" title="Ластхиты + денаи к 10 мин"><b><?php echo e((string) $p['cs']); ?></b><i>CS</i></span>
            <span class="stat-chip" title="Ластхиты к 10 мин"><b><?php echo e((string) ($p['lh10'] ?? 0)); ?></b><i>LH</i></span>
            <span class="stat-chip" title="Денаи к 10 мин"><b><?php echo e((string) ($p['dn10'] ?? 0)); ?></b><i>DN</i></span>
            <span class="stat-chip" title="Золото в минуту к 10 мин"><b><?php echo e((string) ($p['gpm10'] ?? 0)); ?></b><i>GPM</i></span>
            <span class="stat-chip" title="Опыт в минуту к 10 мин"><b><?php echo e((string) ($p['xpm10'] ?? 0)); ?></b><i>XPM</i></span>
        </div>

        <?php if ($spark !== ''): ?>
            <div class="lane-player-spark" title="Накопление ластхитов до 10 мин"><?php echo $spark; ?></div>
        <?php endif; ?>
    </div>
    <?php
}

function build_laning_analysis(array $radiant_players, array $dire_players, array $heroes): array
{
    $by_lane = [
        'top' => ['radiant' => [], 'dire' => []],
        'mid' => ['radiant' => [], 'dire' => []],
        'bot' => ['radiant' => [], 'dire' => []],
    ];

    foreach ([['radiant', $radiant_players], ['dire', $dire_players]] as [$team, $players]) {
        foreach ($players as $player) {
            if ((bool) ($player['is_roaming'] ?? false)) {
                continue;
            }

            $lane = detect_player_lane($player, $team);
            $by_lane[$lane][$team][] = summarize_laning_player($player, $team, $heroes);
        }
    }

    foreach ($by_lane as $key => $data) {
        usort($by_lane[$key]['radiant'], 'sort_laning_players_by_slot');
        usort($by_lane[$key]['dire'], 'sort_laning_players_by_slot');

        $all_players = array_merge($by_lane[$key]['radiant'], $by_lane[$key]['dire']);
        if (empty($all_players)) {
            continue;
        }

        $total_score = 0;
        foreach ($all_players as $p) {
            $total_score += calculate_player_score($p);
        }
        $avg_score = $total_score / count($all_players);

        foreach (['radiant', 'dire'] as $team) {
            foreach ($by_lane[$key][$team] as $i => $p) {
                $player_score = calculate_player_score($p);
                $by_lane[$key][$team][$i]['advantage'] = $avg_score > 0
                    ? (int) round((($player_score - $avg_score) / $avg_score) * 100)
                    : 0;
            }
        }
    }

    $lanes = [];
    $lanes_won = ['radiant' => 0, 'dire' => 0];
    $total_r = 0;
    $total_d = 0;

    foreach ($by_lane as $key => $data) {
        if (empty($data['radiant']) && empty($data['dire'])) {
            continue;
        }
        $analysis = analyze_lane($data['radiant'], $data['dire']);
        if ($analysis['winner'] === 'radiant' || $analysis['winner'] === 'dire') {
            $lanes_won[$analysis['winner']]++;
        }
        $total_r += score_side($data['radiant']);
        $total_d += score_side($data['dire']);
        $lanes[] = [
            'key' => $key,
            'name' => lane_label($key),
            'radiant' => $data['radiant'],
            'dire' => $data['dire'],
            'winner' => $analysis['winner'],
            'winner_label' => $analysis['winner_label'],
            'radiant_pct' => $analysis['radiant_pct'],
        ];
    }

    $ctrl_total = $total_r + $total_d;
    if ($ctrl_total > 0) {
        $ctrl_radiant = (int) round($total_r / $ctrl_total * 100);
    } else {
        $ctrl_radiant = 50;
    }
    $control = ['radiant' => $ctrl_radiant, 'dire' => 100 - $ctrl_radiant];

    return [
        'lanes' => $lanes,
        'lanes_won' => $lanes_won,
        'control' => $control,
    ];
}

function detect_player_lane(array $player, string $team): string
{
    $lane_from_positions = detect_lane_from_position_log($player['lane_pos'] ?? null);
    if ($lane_from_positions !== null) {
        return $lane_from_positions;
    }

    $slot_lane = detect_lane_from_player_slot((int) ($player['player_slot'] ?? -1));
    if ($slot_lane !== null) {
        return $slot_lane;
    }

    $lane_role = (int) ($player['lane_role'] ?? 0);
    if ($lane_role >= 1 && $lane_role <= 3) {
        return lane_role_to_physical_lane($lane_role, $team);
    }

    $lane = (int) ($player['lane'] ?? 0);
    if ($lane >= 1 && $lane <= 3) {
        return lane_role_to_physical_lane($lane, $team);
    }

    return 'mid';
}

function detect_lane_from_position_log(mixed $lane_pos): ?string
{
    if (!is_array($lane_pos) || $lane_pos === []) {
        return null;
    }

    $counts = ['top' => 0, 'mid' => 0, 'bot' => 0];
    foreach ($lane_pos as $key => $value) {
        $lane_value = is_numeric($value) ? (int) $value : (int) $key;
        $weight = is_string($key) && ctype_digit($key) ? 1 : max(1, (int) $value);

        if ($lane_value === 1) {
            $counts['top'] += $weight;
        } elseif ($lane_value === 2) {
            $counts['mid'] += $weight;
        } elseif ($lane_value === 3) {
            $counts['bot'] += $weight;
        }
    }

    arsort($counts);
    $lane = array_key_first($counts);
    return $counts[$lane] > 0 ? $lane : null;
}

function detect_lane_from_player_slot(int $slot): ?string
{
    return match ($slot) {
        0, 1 => 'bot',
        2 => 'mid',
        3, 4 => 'top',
        128, 129 => 'top',
        130 => 'mid',
        131, 132 => 'bot',
        default => null,
    };
}

function lane_role_to_physical_lane(int $lane_role, string $team): string
{
    if ($team === 'radiant') {
        return match ($lane_role) {
            1 => 'bot',
            2 => 'mid',
            3 => 'top',
            default => 'mid',
        };
    }

    return match ($lane_role) {
        1 => 'top',
        2 => 'mid',
        3 => 'bot',
        default => 'mid',
    };
}

function summarize_laning_player(array $player, string $team, array $heroes): array
{
    $lh_t = is_array($player['lh_t'] ?? null) ? $player['lh_t'] : [];
    $dn_t = is_array($player['dn_t'] ?? null) ? $player['dn_t'] : [];
    $gold_t = is_array($player['gold_t'] ?? null) ? $player['gold_t'] : [];
    $xp_t = is_array($player['xp_t'] ?? null) ? $player['xp_t'] : [];

    $last_hits_10 = timeseries_value_at_minute($lh_t, 10, (int) ($player['last_hits'] ?? 0));
    $denies_10 = timeseries_value_at_minute($dn_t, 10, (int) ($player['denies'] ?? 0));
    $gold_10 = timeseries_value_at_minute($gold_t, 10, (int) ($player['total_gold'] ?? 0));
    $xp_10 = timeseries_value_at_minute($xp_t, 10, 0);

    $kills = count_events_before($player['kills_log'] ?? [], 600);
    $deaths = count_events_before($player['deaths_log'] ?? [], 600);

    return [
        'name' => (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним'),
        'account_id' => (int) ($player['account_id'] ?? 0),
        'player_slot' => (int) ($player['player_slot'] ?? 999),
        'hero_img' => get_hero_img((int) ($player['hero_id'] ?? 0), $heroes),
        'team' => $team,
        'cs' => $last_hits_10 + $denies_10,
        'lh10' => $last_hits_10,
        'dn10' => $denies_10,
        'gold' => $gold_10,
        'xp' => $xp_10,
        'gpm10' => (int) round($gold_10 / 10),
        'xpm10' => (int) round($xp_10 / 10),
        'level' => xp_to_level($xp_10),
        'kills' => $kills,
        'deaths' => $deaths,
        'lh_series' => laning_series_slice($lh_t, 11),
        'advantage' => 0,
    ];
}

function sort_laning_players_by_slot(array $a, array $b): int
{
    return ((int) ($a['player_slot'] ?? 999)) <=> ((int) ($b['player_slot'] ?? 999));
}

function timeseries_value_at_minute(array $values, int $minute, int $fallback = 0): int
{
    if ($values === []) {
        return $fallback;
    }

    $index = min($minute, count($values) - 1);
    return (int) ($values[$index] ?? end($values) ?: $fallback);
}

/**
 * Returns the first $count cumulative values of a per-minute time series.
 * Missing points carry the last known value forward. Empty input yields [].
 */
function laning_series_slice(array $values, int $count): array
{
    if ($values === []) {
        return [];
    }

    $out = [];
    $last = 0;
    for ($i = 0; $i < $count; $i++) {
        if (isset($values[$i]) && is_numeric($values[$i])) {
            $last = (int) $values[$i];
        }
        $out[] = $last;
    }

    return $out;
}

function laning_side_cs_series(array $players): array
{
    $out = [];
    foreach ($players as $p) {
        $series = $p['lh_series'] ?? [];
        foreach ($series as $i => $v) {
            $out[$i] = ($out[$i] ?? 0) + (int) $v;
        }
    }
    ksort($out);
    return array_values($out);
}

function count_events_before(array $log, int $seconds): int
{
    $count = 0;
    foreach ($log as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $time = (int) ($entry['time'] ?? 0);
        if ($time >= 0 && $time <= $seconds) {
            $count++;
        }
    }
    return $count;
}

function xp_to_level(int $xp): int
{
    $thresholds = [
        0, 230, 600, 1080, 1680, 2300, 2940, 3600, 4280, 5080,
        5900, 6740, 7640, 8865, 10115, 11390, 12690, 14015, 15415, 16905,
        18505, 20405, 22605, 25105, 27905, 31005, 34405, 38105, 42105, 46405,
    ];
    $level = 1;
    foreach ($thresholds as $i => $t) {
        if ($xp >= $t) {
            $level = $i + 1;
        }
    }
    return min(30, max(1, $level));
}

function calculate_player_score(array $player): int
{
    return ($player['cs'] ?? 0) * 2
        + ($player['level'] ?? 1) * 50
        + ($player['kills'] ?? 0) * 100
        - ($player['deaths'] ?? 0) * 80
        + (int) round(($player['gold'] ?? 0) / 100);
}

function score_side(array $players): int
{
    $score = 0;
    foreach ($players as $p) {
        $score += calculate_player_score($p);
    }
    return max(0, $score);
}

function analyze_lane(array $radiant, array $dire): array
{
    $r_score = score_side($radiant);
    $d_score = score_side($dire);
    $total = $r_score + $d_score;

    if ($total <= 0) {
        return ['winner' => 'draw', 'winner_label' => 'Равная линия', 'radiant_pct' => 50.0];
    }

    $r_pct = ($r_score / $total) * 100;
    $d_pct = 100 - $r_pct;

    if (abs($r_pct - $d_pct) < 5) {
        return ['winner' => 'draw', 'winner_label' => 'Равная линия', 'radiant_pct' => $r_pct];
    }

    if ($r_pct > $d_pct) {
        $diff = (int) round($r_pct - 50);
        return ['winner' => 'radiant', 'winner_label' => 'Свет доминирует (+' . $diff . '%)', 'radiant_pct' => $r_pct];
    }
    $diff = (int) round($d_pct - 50);
    return ['winner' => 'dire', 'winner_label' => 'Тьма доминирует (+' . $diff . '%)', 'radiant_pct' => $r_pct];
}

function lane_label(string $key): string
{
    return match ($key) {
        'top' => 'Верхняя линия (Top)',
        'mid' => 'Центральная линия (Mid)',
        'bot' => 'Нижняя линия (Bot)',
        default => $key,
    };
}

/**
 * Renders a compact area sparkline (SVG) for a cumulative series. Returns '' if
 * there are fewer than two data points.
 */
function laning_sparkline(array $series, string $color): string
{
    $series = array_values(array_map('intval', $series));
    $n = count($series);
    if ($n < 2) {
        return '';
    }

    $w = 132;
    $h = 38;
    $pad = 4;
    $max = max($series);
    $min = min($series);
    if ($max <= $min) {
        $max = $min + 1;
    }
    $range = $max - $min;
    $inner_w = $w - $pad * 2;
    $inner_h = $h - $pad * 2;

    $points = [];
    foreach ($series as $i => $v) {
        $x = $pad + ($inner_w * $i / ($n - 1));
        $y = $pad + $inner_h - ($inner_h * ($v - $min) / $range);
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $line = implode(' ', $points);
    $area = $pad . ',' . ($h - $pad) . ' ' . $line . ' ' . ($w - $pad) . ',' . ($h - $pad);

    $last_x = $pad + $inner_w;
    $last_y = $pad + $inner_h - ($inner_h * (end($series) - $min) / $range);
    $uid = substr(md5($color . $line), 0, 6);

    return '<svg class="ln-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-hidden="true">'
        . '<defs><linearGradient id="g' . $uid . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="' . $color . '" stop-opacity="0.35"/>'
        . '<stop offset="100%" stop-color="' . $color . '" stop-opacity="0"/></linearGradient></defs>'
        . '<polygon points="' . $area . '" fill="url(#g' . $uid . ')"/>'
        . '<polyline points="' . $line . '" fill="none" stroke="' . $color . '" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>'
        . '<circle cx="' . round($last_x, 1) . '" cy="' . round($last_y, 1) . '" r="2.4" fill="' . $color . '"/>'
        . '</svg>';
}

/**
 * Renders a two-line comparison chart (Radiant vs Dire) of cumulative last hits
 * over time. Returns '' if neither side has at least two data points.
 */
function laning_lane_chart(array $r_series, array $d_series): string
{
    $r_series = array_values(array_map('intval', $r_series));
    $d_series = array_values(array_map('intval', $d_series));
    $n = max(count($r_series), count($d_series));
    if ($n < 2) {
        return '';
    }

    $all = array_merge($r_series, $d_series);
    $max = $all === [] ? 1 : max($all);
    if ($max <= 0) {
        $max = 1;
    }

    $w = 560;
    $h = 150;
    $pad_x = 8;
    $pad_y = 12;
    $inner_w = $w - $pad_x * 2;
    $inner_h = $h - $pad_y * 2;

    $make_points = static function (array $series) use ($n, $max, $pad_x, $pad_y, $inner_w, $inner_h): string {
        if ($series === []) {
            return '';
        }
        $count = count($series);
        $last = end($series);
        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $v = $i < $count ? $series[$i] : $last;
            $x = $pad_x + ($inner_w * $i / ($n - 1));
            $y = $pad_y + $inner_h - ($inner_h * $v / $max);
            $points[] = round($x, 1) . ',' . round($y, 1);
        }
        return implode(' ', $points);
    };

    $grid = '';
    foreach ([0.0, 0.5, 1.0] as $frac) {
        $y = round($pad_y + $inner_h - $inner_h * $frac, 1);
        $grid .= '<line x1="' . $pad_x . '" y1="' . $y . '" x2="' . ($w - $pad_x) . '" y2="' . $y . '" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>';
    }

    $svg = '<svg class="ln-lane-chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-hidden="true">' . $grid;

    $r_points = $make_points($r_series);
    if ($r_points !== '') {
        $svg .= '<polyline points="' . $r_points . '" fill="none" stroke="#2ecc71" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
    }
    $d_points = $make_points($d_series);
    if ($d_points !== '') {
        $svg .= '<polyline points="' . $d_points . '" fill="none" stroke="#e74c3c" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
    }

    return $svg . '</svg>';
}
