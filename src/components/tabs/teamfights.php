<?php

declare(strict_types=1);

/** Teamfight view: explicit participation, readable summaries, expandable details. */
function render_teamfights_match_tab(mixed $teamfights, array $radiant_players, array $dire_players, array $heroes): void
{
    if (!is_array($teamfights) || $teamfights === []) {
        echo '<div class="match-insights"><header class="insight-intro"><h2>Командные бои</h2></header><div class="insight-note"><strong>Боевые эпизоды пока недоступны.</strong> Нужен обработанный реплей. Если он уже обработан, в нём могло не быть эпизодов с тремя и более смертями.</div></div>';
        return;
    }
    $slots = index_players_by_slot_for_teamfights($radiant_players, $dire_players);
    $positions = build_teamfight_position_index($radiant_players, $dire_players);
    $summary = summarize_teamfights($teamfights, $slots, $positions, $heroes);
    ?>
    <div class="match-insights teamfight-insights">
        <header class="insight-intro"><span class="insight-eyebrow">РАЗБОР МАТЧА · СРАЖЕНИЯ</span><h2>Командные бои</h2><p>Составы, размены и вклад каждого участника. Только игроки с подтверждёнными боевыми событиями.</p></header>
        <div class="insight-kpis"><div class="insight-kpi"><span class="insight-muted">Эпизоды</span><strong><?php echo e((string) count($teamfights)); ?></strong><small>В хронологическом порядке</small></div><div class="insight-kpi"><span class="insight-muted">В пользу Света</span><strong class="insight-radiant"><?php echo e((string) $summary['radiant_wins']); ?></strong><small>Эпизодов по оценке сайта</small></div><div class="insight-kpi"><span class="insight-muted">В пользу Тьмы</span><strong class="insight-dire"><?php echo e((string) $summary['dire_wins']); ?></strong><small>Остальные эпизоды — без преимущества</small></div></div>
        <details class="insight-details teamfight-method"><summary><span><strong>Кто считается участником и как читать итог</strong><small>Пассивный доход и самолечение не означают участия в бою</small></span><span class="insight-expand" aria-hidden="true">+</span></summary><div class="insight-details-body insight-glossary"><div><h4>Участники</h4><p>Убийство, смерть, нанесённый или полученный урон в противостоянии героев, направленная на противника способность либо лечение союзника, участвующего в бою. Фарм, золото, опыт, выкуп и использование предмета сами по себе не подтверждают участие.</p></div><div><h4>Границы эпизода</h4><p>Парсер объединяет серию смертей с интервалами до 15 секунд и оставляет эпизоды с 3+ смертями. Это временное окно: оно может объединять события в разных частях карты.</p></div><div><h4>Преимущество — оценка сайта</h4><p>Для каждой стороны: убийства минус смерти, плюс 1 при положительном изменении золота участников. Больший результат даёт преимущество. Это эвристика, а не официальный вердикт Dota.</p></div><div><h4>Золото и опыт</h4><p>Δ — изменение за время эпизода у показанных участников. Может включать фарм и пассивный доход; это не только награда за убийства. Лечение союзников не включает самолечение.</p></div></div></details>
        <div class="insight-section-heading"><div><h3>Ход сражений</h3><p>Нажми на эпизод, чтобы увидеть вклад игроков. Первый эпизод уже раскрыт.</p></div></div>
        <div class="teamfights-list">
        <?php foreach ($teamfights as $idx => $fight): render_teamfight_card(analyze_teamfight($fight, $idx, $slots, $positions, $heroes), $heroes); endforeach; ?>
        </div>
    </div>
    <?php
}

function render_teamfight_card(array $breakdown, array $heroes): void
{
    $winner = $breakdown['winner_team'];
    $outcome = $winner === 'draw' ? 'Без преимущества' : 'Преимущество: ' . ($winner === 'radiant' ? 'Свет' : 'Тьма');
    $groups = ['radiant' => [], 'dire' => []];
    foreach ($breakdown['participants'] as $p) {
        if (!empty($p['participated'])) { $groups[$p['team']][] = $p; }
    }
    ?>
    <details class="teamfight-card insight-fight <?php echo e($winner); ?>" data-fight="<?php echo e((string) $breakdown['index']); ?>" <?php echo $breakdown['index'] === 0 ? 'open' : ''; ?>>
        <summary class="fight-summary">
            <div class="fight-time"><span class="insight-eyebrow">ЭПИЗОД <?php echo e((string) ($breakdown['index'] + 1)); ?></span><strong><?php echo e(format_match_tab_time((int) $breakdown['start'])); ?> — <?php echo e(format_match_tab_time((int) $breakdown['end'])); ?></strong><small><?php echo e((string) $breakdown['duration']); ?> сек. · Участников: <?php echo e((string) (count($groups['radiant']) + count($groups['dire']))); ?></small></div>
            <div class="fight-score"><span class="insight-radiant">Свет</span><strong aria-label="Убийства Света и Тьмы"><?php echo e((string) $breakdown['radiant_kills']); ?> <span>:</span> <?php echo e((string) $breakdown['dire_kills']); ?></strong><span class="insight-dire">Тьма</span><small>Убийства в эпизоде</small></div>
            <div class="fight-outcome"><span class="fight-verdict <?php echo e($winner); ?>"><?php echo e($outcome); ?></span><span class="insight-expand" aria-hidden="true">+</span></div>
            <div class="fight-rosters">
            <?php foreach ($groups as $team => $roster): ?>
                <div class="fight-roster"><span class="<?php echo $team === 'radiant' ? 'insight-radiant' : 'insight-dire'; ?>"><?php echo $team === 'radiant' ? 'Свет' : 'Тьма'; ?> · <?php echo e((string) count($roster)); ?></span><div><?php foreach ($roster as $p): ?><img src="<?php echo e(get_hero_img($p['hero_id'], $heroes)); ?>" alt="<?php echo e($p['hero_name']); ?>" title="<?php echo e($p['hero_name'] . ' · ' . $p['persona']); ?>" loading="lazy"><?php endforeach; ?></div></div>
            <?php endforeach; ?>
            </div>
        </summary>
        <div class="fight-detail-body">
            <div class="fight-team-totals" role="region" aria-label="Итоги команд" tabindex="0"><table><caption>Итоги показанных участников за время эпизода</caption><thead><tr><th>Команда</th><th>Убийства</th><th>Урон героям</th><th>Δ золото</th><th>Δ опыт</th></tr></thead><tbody>
            <?php foreach (['radiant' => 'Свет', 'dire' => 'Тьма'] as $team => $label): ?><tr><th scope="row" class="<?php echo $team === 'radiant' ? 'insight-radiant' : 'insight-dire'; ?>"><?php echo e($label); ?></th><td><?php echo e((string) $breakdown[$team . '_kills']); ?></td><td><?php echo e(number_format($breakdown[$team . '_damage'], 0, '.', ' ')); ?></td><td><?php echo e(format_delta_short($breakdown[$team . '_gold'])); ?></td><td><?php echo e(format_delta_short($breakdown[$team . '_xp'])); ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <?php foreach ($groups as $team => $roster): ?>
            <section class="fight-team-participants"><h4 class="<?php echo $team === 'radiant' ? 'insight-radiant' : 'insight-dire'; ?>"><?php echo $team === 'radiant' ? 'Свет' : 'Тьма'; ?> <span class="insight-muted">· Участников: <?php echo e((string) count($roster)); ?></span></h4>
            <?php if ($roster === []): ?><p class="insight-muted">Нет подтверждённых участников.</p><?php else: ?>
            <table class="fight-participants-table"><thead><tr><th>Игрок</th><th>Убийства / смерти</th><th>Урон героям</th><th>Лечение союзников</th><th>Δ золото</th><th>Δ опыт</th></tr></thead><tbody>
            <?php foreach ($roster as $p): ?>
                <tr data-player-slot="<?php echo e((string) $p['slot']); ?>" data-participated="true">
                    <td><div class="insight-player"><img src="<?php echo e(get_hero_img($p['hero_id'], $heroes)); ?>" alt="" loading="lazy"><div><?php if ($p['account_id'] > 0): ?><a href="<?php echo e(player_url($p['account_id'])); ?>"><?php echo e($p['persona']); ?></a><?php else: ?><strong><?php echo e($p['persona']); ?></strong><?php endif; ?><small><?php echo e($p['hero_name']); ?></small></div></div>
                    <?php if ($p['ability_uses'] !== [] || $p['item_uses'] !== [] || $p['buybacks'] > 0): ?><details class="fight-player-actions"><summary>Частые действия</summary><div><?php foreach (['ability_uses' => 'Способность', 'item_uses' => 'Предмет'] as $key => $label): foreach ($p[$key] as $use): ?><span><small><?php echo e($label); ?></small> <?php echo e(short_skill_name($use['name'])); ?> ×<?php echo e((string) $use['count']); ?></span><?php endforeach; endforeach; ?><?php if ($p['buybacks'] > 0): ?><span>Выкуп ×<?php echo e((string) $p['buybacks']); ?></span><?php endif; ?></div></details><?php endif; ?></td>
                    <td data-label="Убийства / смерти" title="<?php echo e($p['kills'] > 0 ? 'Убил: ' . implode(', ', $p['killed_names']) : 'Без убийств'); ?>"><span class="insight-radiant"><?php echo e((string) $p['kills']); ?></span> / <span class="<?php echo $p['deaths'] > 0 ? 'insight-dire' : ''; ?>"><?php echo e((string) $p['deaths']); ?></span></td>
                    <td data-label="Урон героям" class="insight-number"><strong><?php echo e(number_format($p['damage'], 0, '.', ' ')); ?></strong></td>
                    <td data-label="Лечение союзников" class="insight-number"><?php echo $p['combat_healing'] === null ? '—' : e(number_format($p['combat_healing'], 0, '.', ' ')); ?></td>
                    <td data-label="Δ золото" class="insight-number <?php echo $p['gold_delta'] < 0 ? 'insight-dire' : ''; ?>"><?php echo e(format_delta_short($p['gold_delta'])); ?></td>
                    <td data-label="Δ опыт" class="insight-number"><?php echo e(format_delta_short($p['xp_delta'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table><?php endif; ?></section>
            <?php endforeach; ?>
            <p class="fight-footnote">Золото и опыт — за весь интервал, не только за убийства. Действия — самые частые за этот интервал; они не служат самостоятельным признаком участия.</p>
        </div>
    </details>
    <?php
}

function teamfight_player_participated(array $player): bool
{
    if (array_key_exists('participated', $player) && is_bool($player['participated'])) {
        return $player['participated'];
    }
    // Older blobs cannot confirm targeted support: keep only direct evidence.
    return (int) ($player['deaths'] ?? 0) > 0
        || (int) ($player['damage'] ?? 0) > 0
        || (int) ($player['damage_taken'] ?? 0) > 0
        || array_sum(is_array($player['killed'] ?? null) ? $player['killed'] : []) > 0;
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
        if (!teamfight_player_participated($tf_player)) {
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
            'combat_healing' => isset($tf_player['combat_healing']) ? (int) $tf_player['combat_healing'] : null,
            'buybacks' => $buybacks,
            'ability_uses' => $ability_uses,
            'item_uses' => $item_uses,
            'participated' => true,
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
    return ucwords(str_replace('_', ' ', preg_replace('/^item_/', '', $name) ?? $name));
}
