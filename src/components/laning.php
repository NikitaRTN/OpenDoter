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
    $has_data = !empty($laning['lanes']) || !empty($laning['jungle']['radiant']) || !empty($laning['jungle']['dire']);
    $control = $laning['control'];
    $won = $laning['lanes_won'];
    ?>
    <?php render_detailed_stats_gate($match_id, 'статистики лейнинга', $has_data); ?>
    <div data-stats-content <?php echo $has_data ? '' : 'hidden'; ?>>
        <section class="laning-layout" data-laning-root>
            <div class="ln-hero-panel">
                <div class="ln-hero-text">
                    <h2 class="ln-h1">Лейнинг — первые 10 минут</h2>
                    <p class="ln-sub">Кто и насколько уверенно выиграл свою линию. Все цифры — на 10-й минуте: добитые крипы, денаи, золото и опыт в минуту.</p>
                </div>
                <div class="ln-score">
                    <div class="ln-score-item radiant">
                        <span class="ln-score-num"><?php echo e((string) $won['radiant']); ?></span>
                        <span class="ln-score-cap">линий у Света</span>
                    </div>
                    <div class="ln-score-sep"></div>
                    <div class="ln-score-item dire">
                        <span class="ln-score-num"><?php echo e((string) $won['dire']); ?></span>
                        <span class="ln-score-cap">линий у Тьмы</span>
                    </div>
                </div>
            </div>

            <div class="ln-control">
                <div class="ln-control-head">
                    <span class="ln-control-title">Общий контроль линий</span>
                    <span class="ln-control-vals"><b class="r"><?php echo e((string) $control['radiant']); ?>%</b> Свет · <b class="d"><?php echo e((string) $control['dire']); ?>%</b> Тьма</span>
                </div>
                <div class="ln-meter">
                    <div class="ln-meter-fill radiant" style="width: <?php echo e((string) $control['radiant']); ?>%"></div>
                    <div class="ln-meter-fill dire" style="width: <?php echo e((string) $control['dire']); ?>%"></div>
                </div>
            </div>

            <?php foreach ($laning['lanes'] as $lane): ?>
                <?php render_laning_lane_card($lane); ?>
            <?php endforeach; ?>

            <?php render_laning_jungle_group($laning['jungle']); ?>
        </section>
    </div>
    <?php
}

function render_laning_lane_card(array $lane): void
{
    $r_pct = (int) round((float) ($lane['radiant_pct'] ?? 50));
    $r_pct = max(0, min(100, $r_pct));
    $d_pct = 100 - $r_pct;

    $r = $lane['radiant'];
    $d = $lane['dire'];

    $sum = static function (array $players, string $key): int {
        $t = 0;
        foreach ($players as $p) {
            $t += (int) ($p[$key] ?? 0);
        }
        return $t;
    };

    $r_lh = $sum($r, 'lh10');
    $d_lh = $sum($d, 'lh10');
    $r_gold = $sum($r, 'gold');
    $d_gold = $sum($d, 'gold');
    $r_xp = $sum($r, 'xp');
    $d_xp = $sum($d, 'xp');

    $chart = laning_gold_diff_chart(
        laning_side_series($r, 'gold_series'),
        laning_side_series($d, 'gold_series')
    );
    ?>
    <div class="ln-lane <?php echo e($lane['winner']); ?>">
        <div class="ln-lane-head">
            <span class="ln-lane-name"><?php echo e($lane['name']); ?></span>
            <span class="ln-verdict <?php echo e($lane['winner']); ?>"><?php echo e($lane['winner_label']); ?></span>
        </div>

        <div class="ln-lane-meter" title="Контроль этой линии">
            <div class="ln-lane-meter-fill radiant" style="width: <?php echo e((string) $r_pct); ?>%"></div>
            <div class="ln-lane-meter-fill dire" style="width: <?php echo e((string) $d_pct); ?>%"></div>
        </div>

        <div class="ln-cols">
            <div class="ln-col radiant">
                <div class="ln-col-head radiant">Свет</div>
                <?php if (empty($r)): ?>
                    <div class="ln-empty">Нет данных</div>
                <?php else: foreach ($r as $p): render_laning_player($p); endforeach; endif; ?>
            </div>
            <div class="ln-col dire">
                <div class="ln-col-head dire">Тьма</div>
                <?php if (empty($d)): ?>
                    <div class="ln-empty">Нет данных</div>
                <?php else: foreach ($d as $p): render_laning_player($p); endforeach; endif; ?>
            </div>
        </div>

        <div class="ln-vs">
            <div class="ln-vs-title">Сравнение линии к 10-й минуте</div>
            <?php render_laning_vs_bar('Добито крипов', $r_lh, $d_lh, 'Суммарно добитых крипов командой к 10-й минуте'); ?>
            <?php render_laning_vs_bar('Золото', $r_gold, $d_gold, 'Суммарное золото команды к 10-й минуте'); ?>
            <?php render_laning_vs_bar('Опыт', $r_xp, $d_xp, 'Суммарный опыт команды к 10-й минуте'); ?>
        </div>

        <?php if ($chart !== ''): ?>
        <div class="ln-chart">
            <div class="ln-chart-cap">Перевес по золоту по ходу линии</div>
            <div class="ln-diff-wrap">
                <span class="ln-diff-tag top">▲ Свет впереди</span>
                <?php echo $chart; ?>
                <span class="ln-diff-tag bottom">▼ Тьма впереди</span>
            </div>
            <div class="ln-chart-axis"><span>0 мин</span><span>5 мин</span><span>10 мин</span></div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

function render_laning_jungle_group(array $jungle): void
{
    $r = $jungle['radiant'] ?? [];
    $d = $jungle['dire'] ?? [];
    if (empty($r) && empty($d)) {
        return;
    }
    ?>
    <div class="ln-lane draw">
        <div class="ln-lane-head">
            <span class="ln-lane-name">Лес и роуминг</span>
            <span class="ln-verdict draw">Вне линий</span>
        </div>
        <div class="ln-cols">
            <div class="ln-col radiant">
                <div class="ln-col-head radiant">Свет</div>
                <?php if (empty($r)): ?>
                    <div class="ln-empty">Никто не ушёл в лес</div>
                <?php else: foreach ($r as $p): render_laning_player($p); endforeach; endif; ?>
            </div>
            <div class="ln-col dire">
                <div class="ln-col-head dire">Тьма</div>
                <?php if (empty($d)): ?>
                    <div class="ln-empty">Никто не ушёл в лес</div>
                <?php else: foreach ($d as $p): render_laning_player($p); endforeach; endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function render_laning_player(array $p): void
{
    $adv = (int) $p['advantage'];
    $adv_class = $adv > 0 ? 'positive' : ($adv < 0 ? 'negative' : 'neutral');
    ?>
    <div class="ln-player">
        <div class="ln-player-top">
            <?php if ($p['hero_img']): ?>
                <img class="hero-img" src="<?php echo e($p['hero_img']); ?>" alt="">
            <?php endif; ?>
            <div class="ln-player-id">
                <?php if (!empty($p['account_id'])): ?>
                    <a class="player-name" href="<?php echo e(player_url($p['account_id'])); ?>"><?php echo e($p['name']); ?></a>
                <?php else: ?>
                    <span class="player-name"><?php echo e($p['name']); ?></span>
                <?php endif; ?>
                <span class="ln-player-sub">Ур. <?php echo e((string) $p['level']); ?> · Убийства <?php echo e((string) $p['kills']); ?> · Смерти <?php echo e((string) $p['deaths']); ?></span>
            </div>
            <?php if ($adv !== 0): ?>
                <span class="ln-adv <?php echo e($adv_class); ?>" title="Насколько игрок сильнее или слабее среднего по линии"><?php echo $adv > 0 ? '+' : ''; ?><?php echo e((string) $adv); ?>%</span>
            <?php endif; ?>
        </div>

        <div class="ln-metrics">
            <div class="ln-metric" title="Добитые вражеские крипы к 10-й минуте"><b><?php echo e((string) ($p['lh10'] ?? 0)); ?></b><span>Добито крипов</span></div>
            <div class="ln-metric" title="Добитые свои крипы (денаи) к 10-й минуте"><b><?php echo e((string) ($p['dn10'] ?? 0)); ?></b><span>Денаи</span></div>
            <div class="ln-metric" title="Золото в минуту к 10-й минуте"><b><?php echo e((string) ($p['gpm10'] ?? 0)); ?></b><span>Золото/мин</span></div>
            <div class="ln-metric" title="Опыт в минуту к 10-й минуте"><b><?php echo e((string) ($p['xpm10'] ?? 0)); ?></b><span>Опыт/мин</span></div>
        </div>
    </div>
    <?php
}

function render_laning_vs_bar(string $label, int $r, int $d, string $hint): void
{
    $total = $r + $d;
    $r_pct = $total > 0 ? round($r / $total * 100, 1) : 50;
    $d_pct = 100 - $r_pct;
    $lead = $r === $d ? 'draw' : ($r > $d ? 'radiant' : 'dire');
    ?>
    <div class="ln-vs-row" title="<?php echo e($hint); ?>">
        <div class="ln-vs-top">
            <b class="r"><?php echo e(number_format($r, 0, '', ' ')); ?></b>
            <span class="ln-vs-name <?php echo e($lead); ?>"><?php echo e($label); ?></span>
            <b class="d"><?php echo e(number_format($d, 0, '', ' ')); ?></b>
        </div>
        <div class="ln-vs-track">
            <div class="ln-vs-fill r" style="width: <?php echo e((string) $r_pct); ?>%"></div>
            <div class="ln-vs-fill d" style="width: <?php echo e((string) $d_pct); ?>%"></div>
        </div>
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
    $jungle = ['radiant' => [], 'dire' => []];

    foreach ([['radiant', $radiant_players], ['dire', $dire_players]] as [$team, $players]) {
        foreach ($players as $player) {
            $role = detect_player_role($player, $team);
            $summary = summarize_laning_player($player, $team, $heroes);
            if ($role === 'jungle') {
                $jungle[$team][] = $summary;
            } else {
                $by_lane[$role][$team][] = $summary;
            }
        }
    }

    usort($jungle['radiant'], 'sort_laning_players_by_slot');
    usort($jungle['dire'], 'sort_laning_players_by_slot');

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
    $ctrl_radiant = $ctrl_total > 0 ? (int) round($total_r / $ctrl_total * 100) : 50;
    $control = ['radiant' => $ctrl_radiant, 'dire' => 100 - $ctrl_radiant];

    return [
        'lanes' => $lanes,
        'jungle' => $jungle,
        'lanes_won' => $lanes_won,
        'control' => $control,
    ];
}

/**
 * Determines where a player actually spent the laning phase.
 * Returns one of: 'top', 'mid', 'bot', 'jungle'.
 *
 * Priority is given to OpenDota's parsed fields (is_roaming, lane_role, lane)
 * because the player-slot order is NOT a reliable indicator of the physical
 * lane — relying on it previously forced junglers/roamers onto mid.
 */
function detect_player_role(array $player, string $team): string
{
    // Roamers and junglers are not part of a standard lane matchup.
    if ((bool) ($player['is_roaming'] ?? false)) {
        return 'jungle';
    }

    $lane_role = (int) ($player['lane_role'] ?? 0);
    if ($lane_role === 4) {
        return 'jungle';
    }

    // `lane` is the physically observed lane (1 = bottom, 2 = middle, 3 = top).
    $lane = (int) ($player['lane'] ?? 0);
    if ($lane >= 1 && $lane <= 3) {
        return match ($lane) {
            1 => 'bot',
            2 => 'mid',
            3 => 'top',
            default => 'mid',
        };
    }

    // Fall back to the assigned role (1 = safe, 2 = mid, 3 = off) mapped per side.
    if ($lane_role >= 1 && $lane_role <= 3) {
        return lane_role_to_physical_lane($lane_role, $team);
    }

    // Last resort: a rough guess from the player slot. Unknown stays out of the
    // lanes so it never overlaps a real laner.
    return detect_lane_from_player_slot((int) ($player['player_slot'] ?? -1)) ?? 'jungle';
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
        'gold_series' => laning_series_slice($gold_t, 11),
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

function laning_side_series(array $players, string $field): array
{
    $out = [];
    foreach ($players as $p) {
        $series = $p[$field] ?? [];
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
        return ['winner' => 'radiant', 'winner_label' => 'Свет выиграл линию (+' . $diff . '%)', 'radiant_pct' => $r_pct];
    }
    $diff = (int) round($d_pct - 50);
    return ['winner' => 'dire', 'winner_label' => 'Тьма выиграла линию (+' . $diff . '%)', 'radiant_pct' => $r_pct];
}

function lane_label(string $key): string
{
    return match ($key) {
        'top' => 'Верхняя линия',
        'mid' => 'Центральная линия',
        'bot' => 'Нижняя линия',
        default => $key,
    };
}

/**
 * Renders an easy-to-read gold-lead chart. A line above the centre means the
 * Radiant team is ahead on gold; below means Dire is ahead. Returns '' when
 * there is not enough data.
 */
function laning_gold_diff_chart(array $r, array $d): string
{
    $rl = count($r);
    $dl = count($d);
    $n = max($rl, $dl);
    if ($n < 2) {
        return '';
    }

    $rLast = $rl ? (int) end($r) : 0;
    $dLast = $dl ? (int) end($d) : 0;
    $diff = [];
    for ($i = 0; $i < $n; $i++) {
        $rv = $i < $rl ? (int) $r[$i] : $rLast;
        $dv = $i < $dl ? (int) $d[$i] : $dLast;
        $diff[] = $rv - $dv;
    }

    $maxAbs = 0;
    foreach ($diff as $v) {
        $maxAbs = max($maxAbs, abs($v));
    }
    if ($maxAbs <= 0) {
        $maxAbs = 1;
    }

    $w = 640;
    $h = 180;
    $padX = 12;
    $padY = 16;
    $innerW = $w - 2 * $padX;
    $innerH = $h - 2 * $padY;
    $mid = $padY + $innerH / 2;
    $halfH = $innerH / 2;

    $pts = [];
    foreach ($diff as $i => $v) {
        $x = $padX + ($innerW * $i / ($n - 1));
        $y = $mid - $halfH * ($v / $maxAbs);
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $line = implode(' ', $pts);

    $final = end($diff);
    $endColor = $final >= 0 ? '#2ecc71' : '#e74c3c';
    $lastX = $padX + $innerW;
    $lastY = $mid - $halfH * ($final / $maxAbs);

    $svg  = '<svg class="ln-diff" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-hidden="true">';
    $svg .= '<rect x="' . $padX . '" y="' . $padY . '" width="' . $innerW . '" height="' . $halfH . '" fill="rgba(46,204,113,0.10)"/>';
    $svg .= '<rect x="' . $padX . '" y="' . $mid . '" width="' . $innerW . '" height="' . $halfH . '" fill="rgba(231,76,60,0.10)"/>';
    $svg .= '<line x1="' . $padX . '" y1="' . $mid . '" x2="' . ($w - $padX) . '" y2="' . $mid . '" stroke="rgba(255,255,255,0.28)" stroke-width="1" stroke-dasharray="4 4"/>';
    $svg .= '<polyline points="' . $line . '" fill="none" stroke="#f1c40f" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';
    $svg .= '<circle cx="' . round($lastX, 1) . '" cy="' . round($lastY, 1) . '" r="3.4" fill="' . $endColor . '"/>';
    $svg .= '</svg>';

    return $svg;
}
