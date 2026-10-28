<?php

declare(strict_types=1);

/** Compact overview-style fight tables and real combat-log chronology. */
function render_teamfights_match_tab(mixed $teamfights, array $radiant_players, array $dire_players, array $heroes, array $assets = []): void
{
    if (!is_array($teamfights) || $teamfights === []) {
        echo '<div class="match-insights"><header class="insight-intro"><h2>Командные бои</h2></header><div class="insight-note"><strong>Боевые эпизоды пока недоступны.</strong> Нужен обработанный реплей. Если он уже обработан, в нём могло не быть эпизодов с тремя и более смертями.</div></div>';
        return;
    }
    $slots = index_players_by_slot_for_teamfights($radiant_players, $dire_players);
    $positions = build_teamfight_position_index($radiant_players, $dire_players);
    $summary = summarize_teamfights($teamfights, $slots, $positions, $heroes);
    $needs_timeline = count(array_filter($teamfights, static fn($fight) => !is_array($fight) || ($fight['actions_version'] ?? null) !== 1 || !is_array($fight['actions'] ?? null))) > 0;
    $refresh_id = (string) ($assets['match_id'] ?? '');
    ?>
    <div class="match-insights teamfight-insights tf-workspace">
        <header class="tf-heading"><div><span class="insight-eyebrow">РАЗБОР МАТЧА · СРАЖЕНИЯ</span><h2>Командные бои</h2></div><div class="tf-record"><strong><?php echo count($teamfights); ?></strong> эпизодов <span class="insight-radiant">Свет <?php echo $summary['radiant_wins']; ?></span><span class="insight-dire">Тьма <?php echo $summary['dire_wins']; ?></span><small>с преимуществом по оценке сайта</small></div></header>
        <details class="tf-method"><summary>Как читать бои и танкование</summary><p>Эпизод — серия из 3+ смертей с промежутками до 15 секунд и запасом 15 секунд по краям. Это временное окно, а не гарантия одной драки в одном месте. Участие подтверждается взаимодействиями героев, а не фармом или самолечением.</p><p><b>Получено</b> — фактический урон от вражеских героев за эпизод, после уменьшения. Рядом — доля входящего урона команды. Это объём принятого урона, не оценка полезности танкования; сюда не подставляются итоговые счётчики матча. <b>Лечение</b> — союзникам, участвующим в бою, без самолечения.</p><p>Преимущество: убийства минус смерти, плюс 1 при положительном Δ золота участников. Это эвристика сайта. Δ золота и опыта включает весь интервал, в том числе фарм. Журнал показывает зарегистрированные применения, убийства и выкупы, не все нажатия клавиш. Точность времени — секунда; внутри секунды сохранён порядок событий реплея.</p></details>
        <?php if ($needs_timeline && ctype_digit($refresh_id)): ?>
        <div class="tf-refresh" data-tf-refresh data-id="<?php echo e($refresh_id); ?>" data-url="<?php echo e(app_url('api/parse')); ?>" data-status-url="<?php echo e(app_url('api/status/' . $refresh_id)); ?>"><span>Для старого разбора можно восстановить хронологию из сохранённого журнала реплея.</span> <button type="button">Обновить хронологию</button> <span data-tf-refresh-status role="status" aria-live="polite"></span></div>
        <?php endif; ?>
        <div class="teamfights-list">
        <?php foreach ($teamfights as $idx => $fight): render_teamfight_card(analyze_teamfight($fight, $idx, $slots, $positions, $heroes), $heroes, $assets); endforeach; ?>
        </div>
    </div>
    <script defer src="<?php echo e(asset_url('src/assets/teamfights.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../assets/teamfights.js'); ?>"></script>
    <?php
}

function teamfight_action_icon(string $name, string $kind, array $assets = [], int $count = 0): string
{
    $label = short_skill_name($name);
    $source = '';
    if (preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
        $entry = $kind === 'item' ? ($assets['items'][$name] ?? []) : ($assets['abilities'][$name] ?? []);
        $label = (string) ($entry['dname'] ?? $label);
        $img = (string) ($entry['img'] ?? '');
        $source = $img !== '' ? (str_starts_with($img, 'https://') ? explode('?', $img)[0] : 'https://cdn.cloudflare.steamstatic.com' . explode('?', $img)[0]) : 'https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/' . ($kind === 'item' ? 'items/' : 'abilities/') . rawurlencode($name) . '.png';
    }
    $title = ($kind === 'item' ? 'Предмет: ' : 'Способность: ') . $label . ($count > 0 ? ' ×' . $count : '');
    return '<span class="tf-use tf-use-' . e($kind) . '" title="' . e($title) . '"><span class="tf-icon-fallback" aria-hidden="true">?</span>' . ($source !== '' ? '<img src="' . e($source) . '" alt="' . e($label) . '" loading="lazy">' : '<span>' . e($label) . '</span>') . ($count > 1 ? '<b>×' . $count . '</b>' : '') . '</span>';
}

function render_teamfight_card(array $breakdown, array $heroes, array $assets = []): void
{
    $winner = $breakdown['winner_team'];
    $groups = ['radiant' => [], 'dire' => []]; $by_slot = [];
    foreach ($breakdown['participants'] as $p) { $groups[$p['team']][] = $p; $by_slot[$p['slot']] = $p; }
    $id = 'tf-' . $breakdown['index'];
    ?>
    <details class="teamfight-card insight-fight <?php echo e($winner); ?>" data-fight="<?php echo $breakdown['index']; ?>" <?php echo $breakdown['index'] === 0 ? 'open' : ''; ?>>
        <summary class="fight-summary">
            <div class="fight-time"><span class="insight-eyebrow">БОЙ <?php echo $breakdown['index'] + 1; ?></span><strong><?php echo e(format_match_tab_time($breakdown['start'])); ?> — <?php echo e(format_match_tab_time($breakdown['end'])); ?></strong><small><?php echo $breakdown['duration']; ?> сек. · <?php echo count($by_slot); ?> участников</small></div>
            <div class="fight-score"><span class="insight-radiant">Свет</span><strong><?php echo $breakdown['radiant_kills']; ?> : <?php echo $breakdown['dire_kills']; ?></strong><span class="insight-dire">Тьма</span></div>
            <div class="fight-outcome"><span class="fight-verdict <?php echo e($winner); ?>"><?php echo $winner === 'draw' ? 'Без преимущества' : 'Преимущество: ' . ($winner === 'radiant' ? 'Свет' : 'Тьма'); ?></span><span class="insight-expand" aria-hidden="true">+</span></div>
        </summary>
        <div class="fight-detail-body">
            <?php foreach ($groups as $team => $roster):
                $incoming = array_column($roster, 'damage_taken');
                $has_incoming = $incoming !== [] && !in_array(null, $incoming, true);
                $total_incoming = $has_incoming ? array_sum($incoming) : null;
            ?>
            <section class="fight-team-participants"><div class="tf-team-heading"><h4 class="<?php echo $team === 'radiant' ? 'insight-radiant' : 'insight-dire'; ?>"><?php echo $team === 'radiant' ? 'СИЛЫ СВЕТА' : 'СИЛЫ ТЬМЫ'; ?> <small>· <?php echo count($roster); ?></small></h4><div><span>Урон <b><?php echo number_format($breakdown[$team . '_damage'], 0, '.', ' '); ?></b></span><span>Получено <b><?php echo $total_incoming === null ? '—' : number_format($total_incoming, 0, '.', ' '); ?></b></span><span>Δ золото <b><?php echo e(format_delta_short($breakdown[$team . '_gold'])); ?></b></span></div></div>
            <?php if ($roster === []): ?><p class="insight-muted">Нет подтверждённых участников.</p><?php else: ?>
            <div class="tf-swipe-hint">Сдвиньте таблицу влево, чтобы увидеть все показатели →</div><div class="tf-table-scroll" role="region" aria-label="Вклад участников <?php echo $team === 'radiant' ? 'Света' : 'Тьмы'; ?>" tabindex="0"><table class="tf-player-table"><thead><tr><th>Игрок</th><th>У / С</th><th>Урон героям</th><th title="После уменьшения, только от вражеских героев">Получено</th><th>Лечение</th><th>Δ золото</th><th>Δ опыт</th><th>Применения · нажми для хронологии</th></tr></thead><tbody>
            <?php foreach ($roster as $p): ?>
                <tr data-player-slot="<?php echo $p['slot']; ?>" data-participated="true">
                    <td><div class="insight-player"><img src="<?php echo e(get_hero_img($p['hero_id'], $heroes)); ?>" alt="" loading="lazy"><div><?php if ($p['account_id'] > 0): ?><a href="<?php echo e(player_url($p['account_id'])); ?>"><?php echo e($p['persona']); ?></a><?php else: ?><strong><?php echo e($p['persona']); ?></strong><?php endif; ?><small><?php echo e($p['hero_name']); ?></small></div></div></td>
                    <td title="<?php echo e(implode(', ', $p['killed_names'])); ?>"><span class="insight-radiant"><?php echo $p['kills']; ?></span> / <span class="<?php echo $p['deaths'] > 0 ? 'insight-dire' : ''; ?>"><?php echo $p['deaths']; ?></span></td>
                    <td><strong><?php echo number_format($p['damage'], 0, '.', ' '); ?></strong></td>
                    <td class="tf-incoming"><strong><?php echo $p['damage_taken'] === null ? '—' : number_format($p['damage_taken'], 0, '.', ' '); ?></strong><?php if ($total_incoming > 0 && $p['damage_taken'] !== null): ?><small><?php echo round(100 * $p['damage_taken'] / $total_incoming); ?>% команды</small><?php endif; ?></td>
                    <td><?php echo $p['combat_healing'] === null ? '—' : number_format($p['combat_healing'], 0, '.', ' '); ?></td>
                    <td class="<?php echo $p['gold_delta'] < 0 ? 'insight-dire' : ''; ?>"><?php echo e(format_delta_short($p['gold_delta'])); ?></td><td><?php echo e(format_delta_short($p['xp_delta'])); ?></td>
                    <td><button type="button" class="tf-player-uses" data-tf-player="<?php echo $p['slot']; ?>" aria-label="Действия <?php echo e($p['persona']); ?> по времени"><?php foreach (['ability_uses' => 'ability', 'item_uses' => 'item'] as $key => $kind): foreach ($p[$key] as $use): echo teamfight_action_icon($use['name'], $kind, $assets, $use['count']); endforeach; endforeach; ?><?php if ($p['ability_uses'] === [] && $p['item_uses'] === []): ?><span class="insight-muted">Журнал →</span><?php endif; ?></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div><?php endif; ?></section>
            <?php endforeach; ?>
            <section class="tf-timeline" id="<?php echo $id; ?>-log"><div class="tf-log-heading"><h4>Хронология боя <small data-tf-count></small></h4><div class="tf-log-filters"><label>Игрок<select data-tf-filter="player"><option value="all">Все участники</option><?php foreach ($by_slot as $p): ?><option value="<?php echo $p['slot']; ?>"><?php echo e($p['hero_name'] . ' · ' . $p['persona']); ?></option><?php endforeach; ?></select></label><label>События<select data-tf-filter="kind"><option value="all">Все события</option><option value="ability">Способности</option><option value="item">Предметы</option><option value="kill">Убийства</option><option value="buyback">Выкупы</option></select></label></div></div>
            <?php if ($breakdown['actions'] === null): ?><p class="tf-empty">В этом разборе сохранены только количества применений. Для времени и последовательности нужен повторный разбор реплея; порядок из счётчиков не восстанавливается.</p><?php else: ?>
                <div class="tf-event-scroll" role="region" aria-label="События по времени" tabindex="0"><ol class="tf-events">
                <?php foreach ($breakdown['actions'] as $action):
                    $actor = isset($action['slot']) ? ($by_slot[$action['slot']] ?? null) : null; $target = isset($action['target_slot']) ? ($by_slot[$action['target_slot']] ?? null) : null;
                ?><li class="tf-event" data-kind="<?php echo e($action['kind']); ?>" data-actor="<?php echo $action['slot'] ?? -1; ?>" data-target="<?php echo $action['target_slot'] ?? -1; ?>" data-time="<?php echo $action['time']; ?>" data-sequence="<?php echo $action['sequence']; ?>"><time><?php echo e(format_match_tab_time($action['time'])); ?></time><span class="tf-event-actor" title="<?php echo e($actor['persona'] ?? 'Окружение'); ?>"><?php if ($actor): ?><img src="<?php echo e(get_hero_img($actor['hero_id'], $heroes)); ?>" alt=""><?php endif; ?><?php echo e($actor['hero_name'] ?? 'Окружение'); ?></span><span class="tf-event-action"><?php if (in_array($action['kind'], ['ability', 'item'], true)): echo teamfight_action_icon($action['key'], $action['kind'], $assets); ?><span><?php echo e(short_skill_name($action['key'])); ?></span><?php else: ?><b class="<?php echo $action['kind'] === 'kill' ? 'insight-dire' : ''; ?>"><?php echo $action['kind'] === 'kill' ? 'Убийство' : 'Выкуп'; ?></b><?php endif; ?></span><?php if ($target): ?><span class="tf-event-target">→ <img src="<?php echo e(get_hero_img($target['hero_id'], $heroes)); ?>" alt=""><?php echo e($target['hero_name']); ?></span><?php endif; ?></li><?php endforeach; ?>
                </ol></div><p class="tf-empty" data-tf-empty hidden>Нет событий с выбранными фильтрами.</p>
            <?php endif; ?><p class="tf-log-note">Время матча · порядок внутри секунды — по журналу реплея. В таблице — топ-3 способности и предмета; в журнале — все сохранённые применения.</p></section>
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
            'damage_taken' => isset($tf_player['damage_taken']) ? (int) $tf_player['damage_taken'] : null,
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
        'actions' => normalize_teamfight_actions($fight, $position_index),
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

function normalize_teamfight_actions(array $fight, array $positions): ?array
{
    if (($fight['actions_version'] ?? 0) !== 1 || !is_array($fight['actions'] ?? null)) { return null; }
    $result = [];
    foreach ($fight['actions'] as $a) {
        if (!is_array($a) || !in_array($a['kind'] ?? '', ['ability', 'item', 'kill', 'buyback'], true) || !is_numeric($a['time'] ?? null)) { continue; }
        $time = (int) $a['time'];
        if ($time < (int) ($fight['start'] ?? 0) || $time > (int) ($fight['end'] ?? 0)) { continue; }
        if (in_array($a['kind'], ['ability', 'item'], true) && (!is_string($a['key'] ?? null) || !preg_match('/^[a-zA-Z0-9_]+$/D', $a['key']))) { continue; }
        $result[] = ['time' => $time, 'sequence' => (int) ($a['sequence'] ?? count($result)), 'kind' => $a['kind'], 'key' => (string) ($a['key'] ?? ''), 'slot' => isset($a['slot']) ? ($positions[(int) $a['slot']] ?? null) : null, 'target_slot' => isset($a['target_slot']) ? ($positions[(int) $a['target_slot']] ?? null) : null];
    }
    usort($result, static fn(array $a, array $b): int => ($a['time'] <=> $b['time']) ?: ($a['sequence'] <=> $b['sequence']));
    return $result;
}
