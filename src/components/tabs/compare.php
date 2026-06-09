<?php

declare(strict_types=1);

function compare_series_values($value): array
{
    return is_array($value) ? array_values(array_map('intval', $value)) : [];
}

function compare_max_series_len(array $player): int
{
    $max = 1;
    foreach (['gold_t', 'xp_t', 'lh_t', 'dn_t'] as $key) {
        if (is_array($player[$key] ?? null)) {
            $max = max($max, count($player[$key]));
        }
    }
    return $max;
}

function compare_count_log_by_minute(array $log, int $max_len): array
{
    $events = [];
    foreach ($log as $event) {
        if (!is_array($event)) {
            continue;
        }
        $time = (int) ($event['time'] ?? -1);
        if ($time < 0) {
            continue;
        }
        $events[] = $time;
    }
    sort($events);

    $out = [];
    $count = 0;
    $cursor = 0;
    $event_count = count($events);
    for ($minute = 0; $minute < $max_len; $minute++) {
        $limit = $minute * 60;
        while ($cursor < $event_count && $events[$cursor] <= $limit) {
            $count++;
            $cursor++;
        }
        $out[] = $count;
    }

    return $out;
}

function compare_build_deaths_by_slot(array $players, array $heroes): array
{
    $hero_to_slot = [];
    foreach ($players as $player) {
        if (!is_array($player)) {
            continue;
        }
        $hero_id = (int) ($player['hero_id'] ?? 0);
        if ($hero_id > 0 && isset($heroes[$hero_id]['name'])) {
            $hero_to_slot[(string) $heroes[$hero_id]['name']] = (int) ($player['player_slot'] ?? 0);
        }
    }

    $deaths = [];
    foreach ($players as $killer) {
        if (!is_array($killer) || !is_array($killer['kills_log'] ?? null)) {
            continue;
        }
        foreach ($killer['kills_log'] as $event) {
            if (!is_array($event)) {
                continue;
            }
            $victim = (string) ($event['key'] ?? '');
            $slot = $hero_to_slot[$victim] ?? null;
            if ($slot === null) {
                continue;
            }
            $deaths[$slot][] = ['time' => (int) ($event['time'] ?? -1)];
        }
    }

    return $deaths;
}


function compare_interpolate_total_by_minute(int $total, int $max_len): array
{
    $out = [];
    $denom = max(1, $max_len - 1);
    for ($minute = 0; $minute < $max_len; $minute++) {
        $out[] = (int) round($total * ($minute / $denom));
    }
    return $out;
}

function compare_estimate_assists_by_minute(array $players, array $teamfights, int $max_len): array
{
    $events = [];
    foreach ($teamfights as $fight) {
        if (!is_array($fight) || !is_array($fight['players'] ?? null)) {
            continue;
        }
        $fight_players = array_values($fight['players']);
        $death_by_side = ['radiant' => 0, 'dire' => 0];
        for ($i = 0; $i < count($fight_players); $i++) {
            $side = $i < 5 ? 'radiant' : 'dire';
            $death_by_side[$side] += (int) ($fight_players[$i]['deaths'] ?? 0);
        }

        $time = (int) ($fight['last_death'] ?? $fight['end'] ?? $fight['start'] ?? 0);
        if ($time < 0) {
            $time = (int) ($fight['end'] ?? 0);
        }
        $minute = max(0, min($max_len - 1, (int) ceil($time / 60)));

        for ($i = 0; $i < count($fight_players) && $i < count($players); $i++) {
            $side = $i < 5 ? 'radiant' : 'dire';
            $enemy_side = $side === 'radiant' ? 'dire' : 'radiant';
            $enemy_deaths = $death_by_side[$enemy_side];
            if ($enemy_deaths <= 0) {
                continue;
            }
            $fight_player = $fight_players[$i];
            $kills = 0;
            if (is_array($fight_player['killed'] ?? null)) {
                foreach ($fight_player['killed'] as $count) {
                    $kills += (int) $count;
                }
            }
            $contributed = $kills > 0 || (int) ($fight_player['damage'] ?? 0) > 0 || (int) ($fight_player['healing'] ?? 0) > 0;
            if (!$contributed) {
                continue;
            }
            $assist_count = max(0, $enemy_deaths - $kills);
            if ($assist_count > 0) {
                $events[$i][$minute] = ($events[$i][$minute] ?? 0) + $assist_count;
            }
        }
    }

    $series = [];
    foreach ($players as $i => $player) {
        $total_assists = (int) ($player['assists'] ?? 0);
        $raw = [];
        $sum = 0;
        for ($minute = 0; $minute < $max_len; $minute++) {
            $sum += (int) ($events[$i][$minute] ?? 0);
            $raw[] = $sum;
        }

        $estimated_total = max(0, end($raw) ?: 0);
        if ($total_assists <= 0) {
            $series[$i] = array_fill(0, $max_len, 0);
        } elseif ($estimated_total <= 0) {
            $series[$i] = compare_interpolate_total_by_minute($total_assists, $max_len);
        } else {
            $series[$i] = array_map(static fn (int $v): int => min($total_assists, (int) round($v * $total_assists / $estimated_total)), $raw);
        }
    }

    return $series;
}

function render_compare_match_tab(array $radiant_players, array $dire_players, array $heroes, array $match = []): void
{
    $players = array_merge($radiant_players, $dire_players);
    $palette = ['#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#1abc9c', '#3498db', '#9b59b6', '#e84393', '#16a085', '#d35400'];
    $deaths_by_slot = compare_build_deaths_by_slot($players, $heroes);
    $global_max_len = 1;
    foreach ($players as $player) {
        if (is_array($player)) {
            $global_max_len = max($global_max_len, compare_max_series_len($player));
        }
    }
    $assist_series_by_index = compare_estimate_assists_by_minute($players, is_array($match['teamfights'] ?? null) ? $match['teamfights'] : [], $global_max_len);

    $items = [];
    $idx = 0;
    foreach ($players as $player) {
        if (!is_array($player)) {
            continue;
        }
        $hero_id = (int) ($player['hero_id'] ?? 0);
        $is_radiant = isset($player['isRadiant'])
            ? (bool) $player['isRadiant']
            : ((int) ($player['player_slot'] ?? 0) < 128);

        $damage_taken = 0;
        if (is_array($player['damage_taken'] ?? null)) {
            foreach ($player['damage_taken'] as $dt) {
                $damage_taken += (int) $dt;
            }
        }

        $max_len = compare_max_series_len($player);
        $slot = (int) ($player['player_slot'] ?? 0);
        $kills_log = is_array($player['kills_log'] ?? null) ? $player['kills_log'] : [];
        $deaths_log = is_array($player['deaths_log'] ?? null) ? $player['deaths_log'] : ($deaths_by_slot[$slot] ?? []);
        $assists_log = is_array($player['assists_log'] ?? null)
            ? $player['assists_log']
            : (is_array($player['assist_log'] ?? null) ? $player['assist_log'] : []);

        $items[] = [
            'id' => $idx,
            'hero' => get_hero_name($hero_id, $heroes),
            'img' => get_hero_img($hero_id, $heroes),
            'persona' => (string) ($player['personaname'] ?? $player['name'] ?? get_hero_name($hero_id, $heroes)),
            'team' => $is_radiant ? 'radiant' : 'dire',
            'color' => $palette[$idx % count($palette)],
            'series' => [
                'networth' => compare_series_values($player['gold_t'] ?? null),
                'xp' => compare_series_values($player['xp_t'] ?? null),
                'lasthits' => compare_series_values($player['lh_t'] ?? null),
                'denies' => compare_series_values($player['dn_t'] ?? null),
            ],
            'minute_stats' => [
                'kills' => compare_count_log_by_minute($kills_log, $max_len),
                'deaths' => compare_count_log_by_minute($deaths_log, $max_len),
                'assists' => $assists_log !== [] ? compare_count_log_by_minute($assists_log, $max_len) : array_slice($assist_series_by_index[$idx] ?? compare_interpolate_total_by_minute((int) ($player['assists'] ?? 0), $max_len), 0, $max_len),
                'has_assists' => true,
            ],
            'totals' => [
                'hero_damage' => (int) ($player['hero_damage'] ?? 0),
                'hero_healing' => (int) ($player['hero_healing'] ?? 0),
                'tower_damage' => (int) ($player['tower_damage'] ?? 0),
                'damage_taken' => $damage_taken,
                'net_worth' => (int) ($player['net_worth'] ?? $player['total_gold'] ?? 0),
                'gpm' => (int) ($player['gold_per_min'] ?? 0),
                'xpm' => (int) ($player['xp_per_min'] ?? 0),
                'last_hits' => (int) ($player['last_hits'] ?? 0),
                'denies' => (int) ($player['denies'] ?? 0),
                'kills' => (int) ($player['kills'] ?? 0),
                'deaths' => (int) ($player['deaths'] ?? 0),
                'assists' => (int) ($player['assists'] ?? 0),
            ],
        ];
        $idx++;
    }

    if ($items === []) {
        render_match_tab_empty('Нет данных игроков для сравнения.');
        return;
    }

    $payload = json_encode(
        ['players' => $items],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    ?>
    <div id="cmp-root" class="text-main">
        <div class="empty-state"><span>Загрузка интерфейса сравнения…</span></div>
    </div>
    <script id="cmp-data" type="application/json"><?php echo $payload; ?></script>
    <script src="<?php echo e(asset_url('src/assets/compare.js')); ?>" defer></script>
    <?php
}
