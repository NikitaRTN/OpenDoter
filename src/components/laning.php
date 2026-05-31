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
    ?>
    <?php render_detailed_stats_gate($match_id, 'статистики лейнинга', $has_data); ?>
    <div data-stats-content <?php echo $has_data ? '' : 'hidden'; ?>>
        <section class="laning-layout" data-laning-root>
            <div class="laning-header">
                <div>
                    <div class="laning-title">Лейнинг</div>
                    <div class="laning-subtitle">
                        Анализ первых 10 минут: линии определяются по карте и слотам игроков, а не по одному общему lane-полю
                    </div>
                </div>
                <div class="laning-summary">
                    <span class="laning-score radiant">Свет: <strong><?php echo e((string) $laning['lanes_won']['radiant']); ?></strong></span>
                    <span class="laning-score dire">Тьма: <strong><?php echo e((string) $laning['lanes_won']['dire']); ?></strong></span>
                </div>
            </div>

            <?php foreach ($laning['lanes'] as $lane): ?>
                <div class="laning-lane-card <?php echo e($lane['winner']); ?>">
                    <div class="lane-header">
                        <span class="lane-name"><?php echo e($lane['name']); ?></span>
                        <span class="lane-winner <?php echo e($lane['winner']); ?>">
                            <?php echo e($lane['winner_label']); ?>
                        </span>
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
                </div>
            <?php endforeach; ?>
        </section>
    </div>
    <?php
}

function render_laning_player(array $p): void
{
    $adv = (int) $p['advantage'];
    $adv_class = $adv > 0 ? 'positive' : ($adv < 0 ? 'negative' : '');
    ?>
    <div class="lane-player">
        <?php if ($p['hero_img']): ?>
            <img class="hero-img" src="<?php echo e($p['hero_img']); ?>" alt="">
        <?php endif; ?>
        <div class="lane-player-info">
            <?php if (!empty($p['account_id'])): ?>
                <a class="player-name" href="<?php echo e(player_url($p['account_id'])); ?>">
                    <?php echo e($p['name']); ?>
                </a>
            <?php else: ?>
                <span class="player-name"><?php echo e($p['name']); ?></span>
            <?php endif; ?>
            <div class="lane-stats">
                <span title="Добитые и заденаенные крипы к 10 минуте">CS: <strong><?php echo e((string) $p['cs']); ?></strong></span>
                <span title="Золото к 10 минуте">Зол: <strong><?php echo e((string) $p['gold']); ?></strong></span>
                <span title="Уровень к 10 минуте">Ур: <strong><?php echo e((string) $p['level']); ?></strong></span>
                <span title="Убийства / Смерти до 10 минуты">К/Д: <strong><?php echo e($p['kills'] . '/' . $p['deaths']); ?></strong></span>
            </div>
        </div>
        <?php if ($adv !== 0): ?>
            <span class="advantage <?php echo e($adv_class); ?>">
                <?php echo $adv > 0 ? '+' : ''; ?><?php echo e((string) $adv); ?>%
            </span>
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

    foreach ($by_lane as $key => $data) {
        if (empty($data['radiant']) && empty($data['dire'])) {
            continue;
        }
        $analysis = analyze_lane($data['radiant'], $data['dire']);
        if ($analysis['winner'] === 'radiant' || $analysis['winner'] === 'dire') {
            $lanes_won[$analysis['winner']]++;
        }
        $lanes[] = [
            'key' => $key,
            'name' => lane_label($key),
            'radiant' => $data['radiant'],
            'dire' => $data['dire'],
            'winner' => $analysis['winner'],
            'winner_label' => $analysis['winner_label'],
        ];
    }

    return [
        'lanes' => $lanes,
        'lanes_won' => $lanes_won,
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
        'gold' => $gold_10,
        'xp' => $xp_10,
        'level' => xp_to_level($xp_10),
        'kills' => $kills,
        'deaths' => $deaths,
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

    if ($total === 0) {
        return ['winner' => 'draw', 'winner_label' => 'Равная линия'];
    }

    $r_pct = ($r_score / $total) * 100;
    $d_pct = ($d_score / $total) * 100;

    if (abs($r_pct - $d_pct) < 5) {
        return ['winner' => 'draw', 'winner_label' => 'Равная линия'];
    }

    if ($r_pct > $d_pct) {
        $diff = (int) round($r_pct - 50);
        return ['winner' => 'radiant', 'winner_label' => 'Свет доминирует (+' . $diff . '%)'];
    }
    $diff = (int) round($d_pct - 50);
    return ['winner' => 'dire', 'winner_label' => 'Тьма доминирует (+' . $diff . '%)'];
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
