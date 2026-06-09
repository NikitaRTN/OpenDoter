<?php

declare(strict_types=1);

/**
 * Вкладка "Командные бои".
 *
 * Формулы вычисления оставлены без изменений:
 *   - index_players_by_slot_for_teamfights
 *   - build_teamfight_position_index
 *   - summarize_teamfights
 *   - analyze_teamfight
 *   - top_used_skills
 *   - killed_hero_names
 *   - format_delta_short
 *   - short_skill_name
 *
 * Дизайн (render_teamfight_detail / render_teamfight_side) полностью
 * переписан под карточный layout с единой таблицей участников —
 * никаких side-by-side гридов, всё в одной колонке, без overflow.
 */
function render_teamfights_match_tab(
    mixed $teamfights,
    array $radiant_players,
    array $dire_players,
    array $heroes
): void {
    if (!is_array($teamfights) || $teamfights === []) {
        render_match_tab_empty('Teamfight-данные недоступны для этого матча.');
        return;
    }

    $slot_index = index_players_by_slot_for_teamfights($radiant_players, $dire_players);
    $position_index = build_teamfight_position_index($radiant_players, $dire_players);

    $summary = summarize_teamfights($teamfights, $slot_index, $position_index, $heroes);
    ?>
    <div class="laning-summary" style="margin-bottom: 16px;">
        <div class="laning-score">
            <div class="laning-score-item radiant">
                <span class="laning-score-num"><?php echo e((string) $summary['radiant_wins']); ?></span>
                <span>выиграно файтов у Света</span>
            </div>
            <div class="laning-score-item dire">
                <span class="laning-score-num"><?php echo e((string) $summary['dire_wins']); ?></span>
                <span>выиграно файтов у Тьмы</span>
            </div>
        </div>
    </div>
    <div class="teamfights-list">
        <?php foreach ($teamfights as $idx => $fight): ?>
            <?php $breakdown = analyze_teamfight($fight, $idx, $slot_index, $position_index, $heroes); ?>
            <?php render_teamfight_card($breakdown, $heroes); ?>
        <?php endforeach; ?>
    </div>
    <?php
}

function render_teamfight_card(array $breakdown, array $heroes): void
{
    $winner = $breakdown['winner_team'];
    $head_class = $winner === 'draw' ? 'draw' : $winner;
    $winner_label = (string) $breakdown['winner_label'];
    $start = format_match_tab_time((int) $breakdown['start']);
    $duration = format_match_tab_time((int) $breakdown['duration']);
    $deaths_total = (int) ($breakdown['radiant_deaths'] + $breakdown['dire_deaths']);
    ?>
    <div class="teamfight-card">
        <div class="teamfight-card-head <?php echo e($head_class); ?>">
            <div class="teamfight-card-title">
                <span class="teamfight-time"><?php echo e($start); ?></span>
                <span class="teamfight-meta">Длит. <?php echo e($duration); ?> · Погибших: <?php echo e((string) $deaths_total); ?></span>
            </div>
            <span class="laning-verdict <?php echo e($winner); ?>"><?php echo e($winner_label); ?></span>
        </div>
        <div class="teamfight-stats">
            <?php
            $stat = static function (string $label, string $r, string $d): void {
                echo '<span class="teamfight-stat-pair">'
                    . '<span class="label">' . e($label) . '</span>'
                    . '<span class="v r">' . $r . '</span>'
                    . '<span class="vs">vs</span>'
                    . '<span class="v d">' . $d . '</span>'
                    . '</span>';
            };
            $stat('Убийств', (string) $breakdown['radiant_kills'], (string) $breakdown['dire_kills']);
            $stat('Δ зол.', format_delta_short((int) $breakdown['radiant_gold']), format_delta_short((int) $breakdown['dire_gold']));
            $stat('Δ XP', format_delta_short((int) $breakdown['radiant_xp']), format_delta_short((int) $breakdown['dire_xp']));
            $stat('Урон', number_format((int) $breakdown['radiant_damage'], 0, '.', ' '), number_format((int) $breakdown['dire_damage'], 0, '.', ' '));
            ?>
        </div>
        <div class="teamfight-body">
            <table class="teamfight-players">
                <thead>
                <tr>
                    <th class="col-team">Команда</th>
                    <th>Игрок</th>
                    <th class="col-center" title="Убийства в этом файте">У</th>
                    <th class="col-center" title="Смерти в этом файте">С</th>
                    <th class="col-center">Δ зол.</th>
                    <th class="col-center">Δ XP</th>
                    <th class="col-center">Урон</th>
                    <th>Участие</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($breakdown['participants'] as $p): ?>
                    <?php if (empty($p['participated'])) { continue; } ?>
                    <?php $team_label = $p['team'] === 'radiant' ? 'Свет' : 'Тьма'; ?>
                    <tr>
                        <td class="col-team <?php echo e($p['team']); ?>"><?php echo e($team_label); ?></td>
                        <td>
                            <div class="teamfight-player">
                                <?php if (!empty($p['hero_id']) && !empty($p['hero_short'])): ?>
                                    <img class="hero-img" src="<?php echo e(get_hero_img($p['hero_id'], $heroes)); ?>" alt="">
                                <?php endif; ?>
                                <div class="teamfight-player-info">
                                    <?php if (!empty($p['account_id'])): ?>
                                        <a class="name player-name" title="<?php echo e($p['persona']); ?>" href="<?php echo e(player_url($p['account_id'])); ?>"><?php echo e($p['persona']); ?></a>
                                    <?php else: ?>
                                        <div class="name" title="<?php echo e($p['persona']); ?>"><?php echo e($p['persona']); ?></div>
                                    <?php endif; ?>
                                    <div class="hero" title="<?php echo e($p['hero_name']); ?>"><?php echo e($p['hero_name']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="col-center" title="<?php echo e($p['kills'] === 0 ? 'Никого не убил' : 'Убил: ' . implode(', ', $p['killed_names'])); ?>"><?php echo e((string) $p['kills']); ?></td>
                        <td class="col-center col-kda-deaths <?php echo e($p['deaths'] === 0 ? 'zero' : ''); ?>" title="Сколько раз погиб"><?php echo e((string) $p['deaths']); ?></td>
                        <td class="col-center <?php echo e($p['gold_delta'] >= 0 ? 'col-positive' : 'col-negative'); ?>"><?php echo e(format_delta_short($p['gold_delta'])); ?></td>
                        <td class="col-center <?php echo e($p['xp_delta'] >= 0 ? 'col-positive' : 'col-negative'); ?>"><?php echo e(format_delta_short($p['xp_delta'])); ?></td>
                        <td class="col-center"><?php echo e(number_format($p['damage'], 0, '.', ' ')); ?></td>
                        <td>
                            <div class="teamfight-chips">
                                <?php
                                $chips_total = 0;
                                $max_chips = 3;
                                foreach ($p['ability_uses'] as $ab):
                                    if ($chips_total >= $max_chips) break;
                                    $chips_total++;
                                ?>
                                    <span class="teamfight-chip ability" title="<?php echo e($ab['name']); ?> ×<?php echo e((string) $ab['count']); ?>"><?php echo e(short_skill_name($ab['name'])); ?></span>
                                <?php endforeach; ?>
                                <?php foreach ($p['item_uses'] as $it):
                                    if ($chips_total >= $max_chips) break;
                                    $chips_total++;
                                ?>
                                    <span class="teamfight-chip item" title="<?php echo e($it['name']); ?> ×<?php echo e((string) $it['count']); ?>"><?php echo e(short_skill_name($it['name'])); ?></span>
                                <?php endforeach; ?>
                                <?php if ($p['buybacks'] > 0 && $chips_total < $max_chips): ?>
                                    <span class="teamfight-chip buyback" title="Выкупы">BB×<?php echo e((string) $p['buybacks']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function index_players_by_slot_for_teamfights(array $radiant_players, array $dire_players): array
{
    $index = [];
    foreach (['radiant' => $radiant_players, 'dire' => $dire_players] as $team => $players) {
        foreach ($players as $player) {
            if (!is_array($player) || !isset($player['player_slot'])) {
                continue;
            }
            $slot = (int) $player['player_slot'];
            $index[$slot] = [
                'team' => $team,
                'player' => $player,
            ];
        }
    }
    return $index;
}

/**
 * The parsed teamfight blob stores "players" as a positional array whose
 * index 0..4 corresponds to radiant slots 0..4 and 5..9 to dire slots
 * 128..132. Build a parallel lookup so a teamfight player can be mapped
 * back to the real match participant.
 */
function build_teamfight_position_index(array $radiant_players, array $dire_players): array
{
    $index = [];
    $pos = 0;
    foreach ($radiant_players as $player) {
        if (is_array($player) && isset($player['player_slot'])) {
            $index[$pos] = (int) $player['player_slot'];
        }
        $pos++;
    }
    foreach ($dire_players as $player) {
        if (is_array($player) && isset($player['player_slot'])) {
            $index[$pos] = (int) $player['player_slot'];
        }
        $pos++;
    }
    return $index;
}

function summarize_teamfights(array $teamfights, array $slot_index, array $position_index, array $heroes): array
{
    $radiant_wins = 0;
    $dire_wins = 0;
    foreach ($teamfights as $idx => $fight) {
        $b = analyze_teamfight($fight, $idx, $slot_index, $position_index, $heroes);
        if ($b['winner_team'] === 'radiant') {
            $radiant_wins++;
        } elseif ($b['winner_team'] === 'dire') {
            $dire_wins++;
        }
    }
    return ['radiant_wins' => $radiant_wins, 'dire_wins' => $dire_wins];
}

function analyze_teamfight(array $fight, int $idx, array $slot_index, array $position_index, array $heroes): array
{
    $players_data = is_array($fight['players'] ?? null) ? $fight['players'] : [];
    $start = (int) ($fight['start'] ?? 0);
    $end = (int) ($fight['end'] ?? 0);

    $dead_descriptions = [];
    $participants = [];
    $radiant_gold = 0;
    $dire_gold = 0;
    $radiant_kills = 0;
    $dire_kills = 0;
    $radiant_deaths = 0;
    $dire_deaths = 0;
    $radiant_damage = 0;
    $dire_damage = 0;
    $radiant_xp = 0;
    $dire_xp = 0;

    // The teamfight "players" array is positional: index 0..4 = radiant
    // (slots 0..4), 5..9 = dire (slots 128..132). position_index gives us
    // the real player_slot for each position so we can match it back.
    foreach ($players_data as $pos => $tf_player) {
        if (!is_array($tf_player)) {
            continue;
        }
        $slot = $position_index[(int) $pos] ?? null;
        if ($slot === null) {
            continue;
        }
        $info = $slot_index[$slot] ?? null;
        if ($info === null) {
            continue;
        }
        $team = $info['team'];
        $player = $info['player'];
        $hero_id = (int) ($player['hero_id'] ?? 0);
        $hero_name = $hero_id > 0 ? (string) ($heroes[$hero_id]['localized_name'] ?? $heroes[$hero_id]['name'] ?? '?') : '?';
        $hero_short = $hero_id > 0 ? (string) ($heroes[$hero_id]['name'] ?? '') : '';
        $persona = (string) ($player['personaname'] ?? $player['name'] ?? $hero_name);

        $deaths = (int) ($tf_player['deaths'] ?? 0);
        $kills_count = 0;
        $killed_map = is_array($tf_player['killed'] ?? null) ? $tf_player['killed'] : [];
        foreach ($killed_map as $_ => $count) {
            $kills_count += (int) $count;
        }
        $gold_delta = (int) ($tf_player['gold_delta'] ?? 0);
        $xp_delta = (int) ($tf_player['xp_delta'] ?? 0);
        $damage = (int) ($tf_player['damage'] ?? 0);
        $healing = (int) ($tf_player['healing'] ?? 0);
        $buybacks = (int) ($tf_player['buybacks'] ?? 0);

        $ability_uses = top_used_skills($tf_player['ability_uses'] ?? [], 3);
        $item_uses = top_used_skills($tf_player['item_uses'] ?? [], 3);

        $participants[] = [
            'slot' => (int) $slot,
            'team' => $team,
            'persona' => $persona,
            'account_id' => (int) ($player['account_id'] ?? 0),
            'hero_id' => $hero_id,
            'hero_name' => $hero_name,
            'hero_short' => $hero_short,
            'deaths' => $deaths,
            'kills' => $kills_count,
            'killed_names' => killed_hero_names($killed_map, $heroes),
            'gold_delta' => $gold_delta,
            'xp_delta' => $xp_delta,
            'damage' => $damage,
            'healing' => $healing,
            'buybacks' => $buybacks,
            'ability_uses' => $ability_uses,
            'item_uses' => $item_uses,
            'participated' => (
                $deaths > 0 || $kills_count > 0 || $damage > 0 || $healing > 0
                || $gold_delta !== 0 || $xp_delta !== 0 || $buybacks > 0
                || $ability_uses !== [] || $item_uses !== []
            ),
        ];

        if ($deaths > 0) {
            $dead_descriptions[] = sprintf('%s (%s) ×%d', $persona, $hero_name, $deaths);
        }

        if ($team === 'radiant') {
            $radiant_deaths += $deaths;
            $radiant_kills += $kills_count;
            $radiant_gold += $gold_delta;
            $radiant_xp += $xp_delta;
            $radiant_damage += $damage;
        } else {
            $dire_deaths += $deaths;
            $dire_kills += $kills_count;
            $dire_gold += $gold_delta;
            $dire_xp += $xp_delta;
            $dire_damage += $damage;
        }
    }

    // Определяем победителя: команда, у которой больше net-выгоды
    // (kills - deaths) + положительный gold_delta.
    $radiant_score = $radiant_kills - $radiant_deaths + ($radiant_gold > 0 ? 1 : 0);
    $dire_score = $dire_kills - $dire_deaths + ($dire_gold > 0 ? 1 : 0);
    if ($radiant_score > $dire_score) {
        $winner_team = 'radiant';
        $winner_label = 'Свет';
    } elseif ($dire_score > $radiant_score) {
        $winner_team = 'dire';
        $winner_label = 'Тьма';
    } else {
        $winner_team = 'draw';
        $winner_label = 'Равная драка';
    }

    // Сортируем участников команды для удобного отображения
    usort($participants, static function (array $a, array $b): int {
        if ($a['team'] !== $b['team']) {
            return $a['team'] === 'radiant' ? -1 : 1;
        }
        if ($a['deaths'] !== $b['deaths']) {
            return $b['deaths'] <=> $a['deaths'];
        }
        return $b['gold_delta'] <=> $a['gold_delta'];
    });

    return [
        'index' => $idx,
        'start' => $start,
        'end' => $end,
        'duration' => max(0, $end - $start),
        'dead' => $dead_descriptions === [] ? '—' : implode(', ', $dead_descriptions),
        'winner_team' => $winner_team,
        'winner_label' => $winner_label,
        'participants' => $participants,
        'radiant_kills' => $radiant_kills,
        'dire_kills' => $dire_kills,
        'radiant_deaths' => $radiant_deaths,
        'dire_deaths' => $dire_deaths,
        'radiant_gold' => $radiant_gold,
        'dire_gold' => $dire_gold,
        'radiant_xp' => $radiant_xp,
        'dire_xp' => $dire_xp,
        'radiant_damage' => $radiant_damage,
        'dire_damage' => $dire_damage,
    ];
}

function top_used_skills(mixed $uses, int $limit): array
{
    if (!is_array($uses) || $uses === []) {
        return [];
    }
    $items = [];
    foreach ($uses as $name => $count) {
        $items[] = ['name' => (string) $name, 'count' => (int) $count];
    }
    usort($items, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
    return array_slice($items, 0, $limit);
}

function killed_hero_names(array $killed_map, array $heroes): array
{
    $result = [];
    $name_by_key = [];
    foreach ($heroes as $hero) {
        if (!is_array($hero) || empty($hero['name'])) {
            continue;
        }
        $name_by_key[(string) $hero['name']] = (string) ($hero['localized_name'] ?? $hero['name']);
    }
    foreach ($killed_map as $hero_key => $count) {
        $label = $name_by_key[(string) $hero_key] ?? (string) $hero_key;
        $result[] = sprintf('%s ×%d', $label, (int) $count);
    }
    return $result;
}

function format_delta_short(int $value): string
{
    if ($value === 0) {
        return '0';
    }
    $sign = $value > 0 ? '+' : '−';
    return $sign . number_format(abs($value), 0, '.', ' ');
}

function short_skill_name(string $name): string
{
    // Превращаем "pangolier_swashbuckle" -> "swashbuckle" для краткости в чипе
    $name = (string) $name;
    if (str_contains($name, '_')) {
        $parts = explode('_', $name);
        $candidate = $parts[count($parts) - 1] ?? $name;
        if (str_starts_with($candidate, 'recipe_')) {
            $candidate = substr($candidate, 7);
        }
        if (in_array($candidate, ['null', 'tango', 'tpscroll', 'magic_wand', 'bottle', 'blink', 'midas', 'urn', 'dust', 'pipe', 'crimson', 'guardian', 'lotus', 'glimmer', 'force', 'aether'], true)) {
            return $candidate;
        }
        return $candidate;
    }
    return $name;
}
