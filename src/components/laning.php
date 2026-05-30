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
                        Анализ первых 10 минут: кто выиграл линии по CS, золоту, опыту и киллам
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
                                <div class="lane-empty">Нет игроков на линии</div>
                            <?php else: ?>
                                <?php foreach ($lane['radiant'] as $p): ?>
                                    <?php render_laning_player($p); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="lane-vs">VS</div>
                        <div class="lane-side dire">
                            <?php if (empty($lane['dire'])): ?>
                                <div class="lane-empty">Нет игроков на линии</div>
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
            <a class="player-name" href="<?php echo e(player_url($p['account_id'])); ?>">
                <?php echo e($p['name']); ?>
            </a>
            <div class="lane-stats">
                <span title="Добито крипов">CS: <strong><?php echo e((string) $p['cs']); ?></strong></span>
                <span title="Золото за 10 мин.">Зол: <strong><?php echo e((string) $p['gold']); ?></strong></span>
                <span title="Уровень">Ур: <strong><?php echo e((string) $p['level']); ?></strong></span>
                <span title="Убийства / Смерти">К/Д: <strong><?php echo e($p['kills'] . '/' . $p['deaths']); ?></strong></span>
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
            $lane = detect_player_lane($player, $team);
            $by_lane[$lane][$team][] = summarize_laning_player($player, $team, $heroes);
        }
    }

    // Определяем противников на линии — чтобы посчитать индивидуальный "advantage"
    foreach ($by_lane as $key => $data) {
        $all_players = array_merge($data['radiant'], $data['dire']);
        if (empty($all_players)) {
            continue;
        }

        $total_score = 0;
        foreach ($all_players as $p) {
            $total_score += calculate_player_score($p);
        }
        $avg_score = $total_score / count($all_players);

        foreach ($data['radiant'] as $i => $p) {
            $player_score = calculate_player_score($p);
            $by_lane[$key]['radiant'][$i]['advantage'] = $avg_score > 0
                ? (int) round((($player_score - $avg_score) / $avg_score) * 100)
                : 0;
        }
        foreach ($data['dire'] as $i => $p) {
            $player_score = calculate_player_score($p);
            $by_lane[$key]['dire'][$i]['advantage'] = $avg_score > 0
                ? (int) round((($player_score - $avg_score) / $avg_score) * 100)
                : 0;
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

/**
 * Определяем ФИЗИЧЕСКУЮ линию игрока (top / mid / bot).
 *
 * Карта Dota 2: база Света — внизу слева, база Тьмы — вверху справа.
 * Поэтому одна и та же роль даёт разные физические линии для разных команд:
 *   - safe lane:  Свет -> низ (bot), Тьма -> верх (top)
 *   - off lane:   Свет -> верх (top), Тьма -> низ (bot)
 *   - mid:        всегда центр
 *
 * Раньше функция сваливалась в обработку lane_pos как плоского массива, из-за
 * чего array_count_values всегда возвращал доминанту 1 -> 'top', и ВСЕ игроки
 * оказывались на верхней линии. Теперь приоритет сигналов:
 *   1) lane_role (1=safe, 2=mid, 3=off) + сторона команды
 *   2) усреднённая позиция на карте lane_pos (вложенный объект x => y => count)
 *   3) поле lane (роль на линии) + сторона команды
 *   4) player_slot как крайний резерв
 */
function detect_player_lane(array $player, string $team): string
{
    $is_radiant = $team === 'radiant';

    $lane_role = (int) ($player['lane_role'] ?? 0);
    if ($lane_role >= 1 && $lane_role <= 3) {
        return physical_lane_from_role($lane_role, $is_radiant);
    }

    if (!empty($player['lane_pos']) && is_array($player['lane_pos'])) {
        $physical = physical_lane_from_lane_pos($player['lane_pos']);
        if ($physical !== null) {
            return $physical;
        }
    }

    $lane = (int) ($player['lane'] ?? 0);
    if ($lane >= 1 && $lane <= 3) {
        return physical_lane_from_role($lane, $is_radiant);
    }

    $slot = (int) ($player['player_slot'] ?? -1);
    $order = $slot >= 128 ? $slot - 128 : $slot;
    if ($order >= 0 && $order <= 4) {
        $role = $order <= 1 ? 1 : ($order === 2 ? 2 : 3);
        return physical_lane_from_role($role, $is_radiant);
    }

    return 'mid';
}

function physical_lane_from_role(int $role, bool $is_radiant): string
{
    if ($role === 2) {
        return 'mid';
    }
    if ($role === 1) {
        // safe lane
        return $is_radiant ? 'bot' : 'top';
    }
    // off lane (role 3)
    return $is_radiant ? 'top' : 'bot';
}

/**
 * lane_pos в OpenDota — вложенный объект вида { x => { y => count } }, где x и y —
 * координаты на карте (~64..192, центр ~128). Считаем взвешенный центр позиций и
 * определяем линию геометрически (не зависит от стороны команды — это физическая
 * линия на карте):
 *   - верх-слева  (y > x) -> top
 *   - низ-справа  (x > y) -> bot
 *   - по диагонали (x ≈ y) -> mid
 */
function physical_lane_from_lane_pos(array $lane_pos): ?string
{
    $sum_x = 0.0;
    $sum_y = 0.0;
    $weight = 0.0;

    foreach ($lane_pos as $x => $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach ($row as $y => $count) {
            $c = (float) $count;
            if ($c <= 0) {
                continue;
            }
            $sum_x += ((float) $x) * $c;
            $sum_y += ((float) $y) * $c;
            $weight += $c;
        }
    }

    if ($weight <= 0) {
        return null;
    }

    $avg_x = $sum_x / $weight;
    $avg_y = $sum_y / $weight;
    $diagonal = $avg_y - $avg_x;

    if (abs($diagonal) <= 16.0) {
        return 'mid';
    }

    return $diagonal > 0 ? 'top' : 'bot';
}

function summarize_laning_player(array $player, string $team, array $heroes): array
{
    $lh_t = is_array($player['lh_t'] ?? null) ? $player['lh_t'] : [];
    $dn_t = is_array($player['dn_t'] ?? null) ? $player['dn_t'] : [];
    $gpm_t = is_array($player['gpm_t'] ?? null) ? $player['gpm_t'] : [];
    $xp_t = is_array($player['xp_t'] ?? null) ? $player['xp_t'] : [];
    $gold_t = is_array($player['gold_t'] ?? null) ? $player['gold_t'] : [];

    $cs = 0;
    if (!empty($lh_t)) {
        $cs += (int) array_sum(array_slice($lh_t, 0, 10));
    }
    if (!empty($dn_t)) {
        $cs += (int) array_sum(array_slice($dn_t, 0, 10));
    }

    $gold = 0;
    if (!empty($gold_t)) {
        $gold = (int) ($gold_t[9] ?? end($gold_t) ?? 0);
    } elseif (!empty($gpm_t)) {
        $gold = (int) array_sum(array_slice($gpm_t, 0, 10));
    }

    $xp = 0;
    if (!empty($xp_t)) {
        $xp = (int) ($xp_t[9] ?? end($xp_t) ?? 0);
    }

    $level = isset($player['level']) && is_int($player['level']) && $player['level'] > 0
        ? (int) $player['level']
        : xp_to_level($xp);

    $kills = count_kills_before($player['kills_log'] ?? [], 600);
    $deaths_log = $player['killed_by'] ?? $player['deaths_log'] ?? [];
    $deaths = count_kills_before($deaths_log, 600);

    return [
        'name' => (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним'),
        'account_id' => (int) ($player['account_id'] ?? 0),
        'hero_img' => get_hero_img((int) ($player['hero_id'] ?? 0), $heroes),
        'team' => $team,
        'cs' => $cs,
        'gold' => $gold,
        'xp' => $xp,
        'level' => $level,
        'kills' => $kills,
        'deaths' => $deaths,
        'advantage' => 0,
    ];
}

function count_kills_before(array $log, int $seconds): int
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
