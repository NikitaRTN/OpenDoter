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
        <?php render_match_tab_section(
            'Лейнинг',
            'первые 10 минут — кто и насколько уверенно выиграл свою линию',
            static function () use ($won, $control): void {
                render_laning_summary($won, $control);
            }
        ); ?>

        <?php foreach ($laning['lanes'] as $lane): ?>
            <?php render_laning_lane_section($lane, $heroes); ?>
        <?php endforeach; ?>

        <?php render_laning_jungle_section($laning['jungle'], $heroes); ?>
    </div>
    <?php
}

function render_laning_summary(array $won, array $control): void
{
    ?>
    <div class="laning-score">
        <div class="laning-score-item radiant">
            <span class="laning-score-num"><?php echo e((string) $won['radiant']); ?></span>
            <span>линий у Света</span>
        </div>
        <div class="laning-score-item dire">
            <span class="laning-score-num"><?php echo e((string) $won['dire']); ?></span>
            <span>линий у Тьмы</span>
        </div>
    </div>

    <div class="laning-control">
        <div class="laning-control-head">
            <span class="laning-control-title">Общий контроль линий</span>
            <span class="laning-control-vals"><b class="r"><?php echo e((string) $control['radiant']); ?>%</b> Свет · <b class="d"><?php echo e((string) $control['dire']); ?>%</b> Тьма</span>
        </div>
        <div class="laning-bar">
            <div class="laning-bar-seg radiant" style="width: <?php echo e((string) $control['radiant']); ?>%"></div>
            <div class="laning-bar-seg dire" style="width: <?php echo e((string) $control['dire']); ?>%"></div>
        </div>
    </div>
    <?php
}

function render_laning_lane_section(array $lane, array $heroes): void
{
    $r = $lane['radiant'];
    $d = $lane['dire'];
    $r_pct = (int) round((float) ($lane['radiant_pct'] ?? 50));
    $r_pct = max(0, min(100, $r_pct));
    $d_pct = 100 - $r_pct;

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
    <section class="mb-5 rounded-lg border border-line bg-panel p-4">
        <div class="mb-3 flex items-center justify-between border-b border-line pb-1.5">
            <div>
                <span class="text-base font-bold uppercase tracking-wide text-main"><?php echo e($lane['name']); ?></span>
                <span class="text-muted"> - первые 10 минут</span>
            </div>
            <span class="laning-verdict <?php echo e($lane['winner']); ?>"><?php echo e($lane['winner_label']); ?></span>
        </div>

        <div class="laning-control">
            <div class="laning-control-head">
                <span class="laning-control-title">Контроль линии</span>
                <span class="laning-control-vals"><b class="r"><?php echo e((string) $r_pct); ?>%</b> Свет · <b class="d"><?php echo e((string) $d_pct); ?>%</b> Тьма</span>
            </div>
            <div class="laning-bar">
                <div class="laning-bar-seg radiant" style="width: <?php echo e((string) $r_pct); ?>%"></div>
                <div class="laning-bar-seg dire" style="width: <?php echo e((string) $d_pct); ?>%"></div>
            </div>
        </div>

        <?php if (empty($r) && empty($d)): ?>
            <div class="empty-state">Нет данных по этой линии.</div>
        <?php else: ?>
            <table class="overview-table">
                <thead>
                    <tr>
                        <th>Игрок</th>
                        <th class="col-center">Команда</th>
                        <th class="col-center">Ур.</th>
                        <th class="col-center">У / С</th>
                        <th class="col-center">LH10</th>
                        <th class="col-center">Денаи10</th>
                        <th class="col-center">GPM10</th>
                        <th class="col-center">XPM10</th>
                        <th class="col-center" title="Насколько игрок сильнее или слабее среднего по линии">Adv%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($r as $p): ?>
                        <?php render_laning_player_row($p, 'radiant', $heroes); ?>
                    <?php endforeach; ?>
                    <?php foreach ($d as $p): ?>
                        <?php render_laning_player_row($p, 'dire', $heroes); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="team-header laning-vs-heading" style="margin-top: 18px;">
            <div>
                <span class="team-title">Сравнение к 10-й минуте</span>
            </div>
        </div>
        <div class="laning-vs">
            <?php render_laning_vs_bar('Добито крипов', $r_lh, $d_lh, 'Суммарно добитых крипов командой к 10-й минуте'); ?>
            <?php render_laning_vs_bar('Золото', $r_gold, $d_gold, 'Суммарное золото команды к 10-й минуте'); ?>
            <?php render_laning_vs_bar('Опыт', $r_xp, $d_xp, 'Суммарный опыт команды к 10-й минуте'); ?>
        </div>

        <?php if ($chart !== ''): ?>
            <div class="laning-chart-wrap">
                <div class="laning-chart-cap">Перевес по золоту по ходу линии</div>
                <span class="laning-chart-tag top">▲ Свет впереди</span>
                <?php echo $chart; ?>
                <span class="laning-chart-tag bottom">▼ Тьма впереди</span>
                <div class="laning-chart-axis"><span>0 мин</span><span>5 мин</span><span>10 мин</span></div>
            </div>
        <?php endif; ?>
    </section>
    <?php
}

function render_laning_jungle_section(array $jungle, array $heroes): void
{
    $r = $jungle['radiant'] ?? [];
    $d = $jungle['dire'] ?? [];
    if (empty($r) && empty($d)) {
        return;
    }
    ?>
    <section class="mb-5 rounded-lg border border-line bg-panel p-4">
        <div class="mb-3 flex items-center justify-between border-b border-line pb-1.5">
            <div>
                <span class="text-base font-bold uppercase tracking-wide text-main">Лес и роуминг</span>
                <span class="text-muted"> - вне стандартных линий</span>
            </div>
        </div>
        <table class="overview-table">
            <thead>
                <tr>
                    <th>Игрок</th>
                    <th class="col-center">Команда</th>
                    <th class="col-center">Ур.</th>
                    <th class="col-center">У / С</th>
                    <th class="col-center">LH10</th>
                    <th class="col-center">Денаи10</th>
                    <th class="col-center">GPM10</th>
                    <th class="col-center">XPM10</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($r as $p): ?>
                    <?php render_laning_player_row($p, 'radiant', $heroes); ?>
                <?php endforeach; ?>
                <?php foreach ($d as $p): ?>
                    <?php render_laning_player_row($p, 'dire', $heroes); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
}

function render_laning_player_row(array $p, string $team, array $heroes): void
{
    $is_radiant = $team === 'radiant';
    $adv = (int) ($p['advantage'] ?? 0);
    $adv_class = $adv > 0 ? 'positive' : ($adv < 0 ? 'negative' : 'neutral');
    $hero_id = (int) ($p['hero_id'] ?? 0);
    ?>
    <tr>
        <td>
            <div class="player-cell">
                <?php if (!empty($p['hero_img'])): ?>
                    <img class="hero-img" src="<?php echo e($p['hero_img']); ?>" alt="">
                <?php else: ?>
                    <span class="missing-asset">Нет героя</span>
                <?php endif; ?>
                <div class="player-info">
                    <?php if (!empty($p['account_id'])): ?>
                        <a class="player-name" href="<?php echo e(player_url($p['account_id'])); ?>"><?php echo e($p['name']); ?></a>
                    <?php else: ?>
                        <span class="player-name"><?php echo e($p['name']); ?></span>
                    <?php endif; ?>
                    <span class="laning-player-sub"><?php echo e(get_hero_name($hero_id, $heroes)); ?></span>
                </div>
            </div>
        </td>
        <td class="col-center <?php echo $is_radiant ? 'radiant-title' : 'dire-title'; ?>"><?php echo $is_radiant ? 'Свет' : 'Тьма'; ?></td>
        <td class="col-center"><?php echo e((string) ($p['level'] ?? 0)); ?></td>
        <td class="col-center" title="Убийства / смерти за первые 10 минут матча"><?php
            $kills = $p['kills'] ?? null;
            $deaths = $p['deaths'] ?? null;
            if ($kills === null || $deaths === null) {
                echo '—';
            } else {
                echo e((string) $kills) . ' / ' . e((string) $deaths);
            }
        ?></td>
        <td class="col-center"><?php echo e((string) ($p['lh10'] ?? 0)); ?></td>
        <td class="col-center"><?php echo e((string) ($p['dn10'] ?? 0)); ?></td>
        <td class="col-center"><?php echo e((string) ($p['gpm10'] ?? 0)); ?></td>
        <td class="col-center"><?php echo e((string) ($p['xpm10'] ?? 0)); ?></td>
        <td class="col-center laning-adv <?php echo e($adv_class); ?>" title="Насколько игрок сильнее или слабее среднего по линии">
            <?php
            if ($adv === 0) {
                echo '—';
            } else {
                echo ($adv > 0 ? '+' : '') . e((string) $adv) . '%';
            }
            ?>
        </td>
    </tr>
    <?php
}

function render_laning_vs_bar(string $label, int $r, int $d, string $hint): void
{
    $max_value = max(1, $r, $d);
    $r_pct = round($r / $max_value * 100, 1);
    $d_pct = round($d / $max_value * 100, 1);
    $lead = $r === $d ? 'draw' : ($r > $d ? 'radiant' : 'dire');
    ?>
    <div class="laning-vs-card <?php echo e($lead); ?>" title="<?php echo e($hint); ?>">
        <div class="laning-vs-card-head">
            <span class="laning-vs-title"><?php echo e($label); ?></span>
            <span class="laning-vs-leader <?php echo e($lead); ?>">
                <?php echo $lead === 'draw' ? 'Ровно' : ($lead === 'radiant' ? 'Свет впереди' : 'Тьма впереди'); ?>
            </span>
        </div>

        <div class="laning-vs-side radiant">
            <div class="laning-vs-side-head">
                <span class="laning-vs-team">Свет</span>
                <b class="laning-vs-value"><?php echo e(number_format($r, 0, '', ' ')); ?></b>
            </div>
            <div class="laning-vs-track" aria-label="<?php echo e($label); ?>, Свет">
                <div class="laning-vs-fill r" style="width: <?php echo e((string) $r_pct); ?>%"></div>
            </div>
        </div>

        <div class="laning-vs-side dire">
            <div class="laning-vs-side-head">
                <span class="laning-vs-team">Тьма</span>
                <b class="laning-vs-value"><?php echo e(number_format($d, 0, '', ' ')); ?></b>
            </div>
            <div class="laning-vs-track" aria-label="<?php echo e($label); ?>, Тьма">
                <div class="laning-vs-fill d" style="width: <?php echo e((string) $d_pct); ?>%"></div>
            </div>
        </div>
    </div>
    <?php
}

function build_laning_analysis(array &$radiant_players, array &$dire_players, array $heroes): array
{
    // The local parser does not emit a per-player deaths_log, but every kill
    // is recorded in the killer's kills_log as { time, key: <victim hero> }.
    // Synthesize a deaths_log for each player by counting how many times the
    // opposing team killed them so the laning summary can show real K/D over
    // the first 10 minutes.
    //
    // The arrays are passed by reference because PHP array_merge would create
    // a new array and any mutations there would not be visible to the original
    // callers used by summarize_laning_player below.
    $build_deaths_log = static function (array &$players, array $all_players, array $heroes): void {
        // Build hero_name -> player_slot map for the current side.
        $hero_to_player = [];
        foreach ($all_players as $player) {
            if (!is_array($player)) {
                continue;
            }
            $hero_id = (int) ($player['hero_id'] ?? 0);
            if ($hero_id > 0 && isset($heroes[$hero_id]['name'])) {
                $hero_to_player[(string) $heroes[$hero_id]['name']] = (int) ($player['player_slot'] ?? 0);
            }
        }

        // For every kill on the opposing side check if the victim is on this
        // side, and if so append a death event to that player's deaths_log.
        foreach ($all_players as $killer) {
            if (!is_array($killer) || !is_array($killer['kills_log'] ?? null)) {
                continue;
            }
            foreach ($killer['kills_log'] as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $time = (int) ($event['time'] ?? 0);
                $victim = (string) ($event['key'] ?? '');
                if ($victim === '' || $time < 0 || $time > 600) {
                    continue;
                }
                $victim_slot = $hero_to_player[$victim] ?? null;
                if ($victim_slot === null) {
                    continue;
                }
                foreach ($players as &$target) {
                    if (!is_array($target)) {
                        continue;
                    }
                    if ((int) ($target['player_slot'] ?? 0) === $victim_slot) {
                        if (!isset($target['deaths_log']) || !is_array($target['deaths_log'])) {
                            $target['deaths_log'] = [];
                        }
                        $target['deaths_log'][] = ['time' => $time];
                        break;
                    }
                }
                unset($target);
            }
        }
    };

    $all_players = array_merge($radiant_players, $dire_players);
    $build_deaths_log($radiant_players, $all_players, $heroes);
    $build_deaths_log($dire_players, $all_players, $heroes);

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
 * Order of trust:
 *   1. Explicit parsed fields (is_roaming / lane_role / lane), when present.
 *   2. Otherwise, derive them from `lane_pos` using OpenDota's own algorithm.
 *      Many self-hosted parsers emit only the raw `lane_pos` position heatmap
 *      and omit lane/lane_role/is_roaming, which previously forced every player
 *      onto a slot-based guess (junglers shown on mid, mid on top, and so on).
 */
function detect_player_role(array $player, string $team): string
{
    if (
        !isset($player['lane'])
        && !isset($player['lane_role'])
        && is_array($player['lane_pos'] ?? null)
        && $player['lane_pos'] !== []
    ) {
        $derived = lane_from_lane_pos($player['lane_pos'], $team === 'radiant');
        if ($derived['lane'] > 0) {
            $player['lane'] = $derived['lane'];
            $player['lane_role'] = $derived['lane_role'];
            if (!isset($player['is_roaming'])) {
                $player['is_roaming'] = $derived['is_roaming'];
            }
        }
    }

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

/**
 * Ports OpenDota's lane detection (svc/util/compute.ts getLaneFromPosData).
 * Buckets every position sample in `lane_pos` into a lane region via the
 * laneMappings grid, then returns the dominant lane plus the derived role and
 * a roaming flag (dominant lane present <45% of the time => roaming).
 *
 * Lane constants: 1 = bottom, 2 = mid, 3 = top, 4 = Radiant jungle, 5 = Dire jungle.
 */
function lane_from_lane_pos(array $lane_pos, bool $is_radiant): array
{
    $map = laning_lane_mappings();
    $counts = [];
    $total = 0;

    foreach ($lane_pos as $x => $ys) {
        if (!is_array($ys)) {
            continue;
        }
        $adj_x = (int) $x - 64;
        foreach ($ys as $y => $val) {
            $val = (int) $val;
            if ($val <= 0) {
                continue;
            }
            $adj_y = 192 - (int) $y; // 128 - ((int) $y - 64)
            if ($adj_x < 0 || $adj_x > 127 || $adj_y < 0 || $adj_y > 127) {
                continue;
            }
            $lane = $map[$adj_y][$adj_x];
            $counts[$lane] = ($counts[$lane] ?? 0) + $val;
            $total += $val;
        }
    }

    if ($total === 0 || $counts === []) {
        return ['lane' => 0, 'lane_role' => 0, 'is_roaming' => false];
    }

    arsort($counts);
    $lane = (int) array_key_first($counts);
    $mode_count = (int) $counts[$lane];

    $lane_roles = [
        1 => $is_radiant ? 1 : 3,
        2 => 2,
        3 => $is_radiant ? 3 : 1,
        4 => 4,
        5 => 4,
    ];

    return [
        'lane' => $lane,
        'lane_role' => $lane_roles[$lane] ?? 0,
        'is_roaming' => ($mode_count / $total) < 0.45,
    ];
}

/**
 * Builds OpenDota's 128x128 lane-region lookup grid (laneMappings.ts), indexed
 * as $map[adjY][adjX]. Computed once per request.
 */
function laning_lane_mappings(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = [];
    for ($i = 0; $i < 128; $i++) {
        $row = [];
        for ($j = 0; $j < 128; $j++) {
            if (abs($i - (127 - $j)) < 8) {
                $lane = 2; // mid
            } elseif ($j < 27 || $i < 27) {
                $lane = 3; // top
            } elseif ($j >= 100 || $i >= 100) {
                $lane = 1; // bot
            } elseif ($i < 50) {
                $lane = 5; // dire jungle
            } elseif ($i >= 77) {
                $lane = 4; // radiant jungle
            } else {
                $lane = 2; // mid
            }
            $row[] = $lane;
        }
        $map[] = $row;
    }

    return $map;
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

    // Kills/deaths during the laning phase (first 10 minutes) are only
    // available in the parsed replay. We check if parsed data is present
    // (like lh_t or kills_log). If it is, an empty log just means 0 events.
    $is_parsed = array_key_exists('lh_t', $player) || array_key_exists('kills_log', $player);

    $kills = null;
    $deaths = null;
    
    if ($is_parsed) {
        $kills_log = is_array($player['kills_log'] ?? null) ? $player['kills_log'] : [];
        $deaths_log = is_array($player['deaths_log'] ?? null) ? $player['deaths_log'] : [];
        $kills = count_events_before($kills_log, 600);
        $deaths = count_events_before($deaths_log, 600);
    }

    $has_laning_kda = $kills !== null && $deaths !== null;

    return [
        'name' => (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним'),
        'account_id' => (int) ($player['account_id'] ?? 0),
        'player_slot' => (int) ($player['player_slot'] ?? 999),
        'hero_id' => (int) ($player['hero_id'] ?? 0),
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
        'kda_partial' => $has_laning_kda,
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

    $svg  = '<svg class="laning-chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-hidden="true">';
    $svg .= '<rect x="' . $padX . '" y="' . $padY . '" width="' . $innerW . '" height="' . $halfH . '" fill="rgba(46,204,113,0.10)"/>';
    $svg .= '<rect x="' . $padX . '" y="' . $mid . '" width="' . $innerW . '" height="' . $halfH . '" fill="rgba(231,76,60,0.10)"/>';
    $svg .= '<line x1="' . $padX . '" y1="' . $mid . '" x2="' . ($w - $padX) . '" y2="' . $mid . '" stroke="rgba(255,255,255,0.28)" stroke-width="1" stroke-dasharray="4 4"/>';
    $svg .= '<polyline points="' . $line . '" fill="none" stroke="#f1c40f" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>';
    $svg .= '<circle cx="' . round($lastX, 1) . '" cy="' . round($lastY, 1) . '" r="3.4" fill="' . $endColor . '"/>';
    $svg .= '</svg>';

    return $svg;
}
