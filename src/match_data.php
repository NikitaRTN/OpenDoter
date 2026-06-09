<?php

declare(strict_types=1);

function load_match_context(array $config, ?string $requested_match_id = null): array
{
    $api_base = rtrim((string) $config['api_base'], '/');
    $match_id = $requested_match_id ?: (string) $config['match_id'];
    $timeout = (float) $config['request_timeout'];
    $cache_ttl = (int) ($config['match_context_cache_ttl'] ?? 0);
    $cache_key = 'match_' . $match_id . '_v2';

    $cached = app_cache_get('match_context', $cache_key, $cache_ttl);
    if ($cached !== null) {
        return $cached;
    }

    // Сначала пробуем полные распарсенные данные. Если их нет — берём
    // лёгкую мета из OpenDota (через локальный API), чтобы отрендерить
    // «главную» страницу с кнопкой обработки вместо 404.
    $match_response = fetch_first_optional_json([
        "{$api_base}/api/match/{$match_id}?full=1",
        "{$api_base}/api/match/{$match_id}",
    ], $timeout);
    $parse_status = 'not_found';
    if (!empty($match_response['match']) && is_array($match_response['match'])) {
        $parse_status = 'parsed';
    } else {
        $meta = fetch_json("{$api_base}/api/match/{$match_id}/metadata", $timeout);
        if ($meta['ok'] && is_array($meta['data']) && !empty($meta['data']['match_id'])) {
            $match_response = ['match' => $meta['data'], 'parsed' => []];
            $parse_status = 'metadata';
        }
    }

    if ($parse_status === 'not_found') {
        throw new RuntimeException("Матч {$match_id} не найден ни в локальном кэше, ни в OpenDota.");
    }

    $constants_cache_ttl = (int) ($config['constants_cache_ttl'] ?? 21600);
    $heroes = fetch_constants_cached('heroes', [
        "{$api_base}/constants/heroes.json",
        "{$api_base}/constants/heroes",
    ], $timeout, $constants_cache_ttl, true, 'Герои');
    $items = fetch_constants_cached('items', [
        "{$api_base}/constants/items.json",
        "{$api_base}/constants/items",
    ], $timeout, $constants_cache_ttl, true, 'Предметы');
    $abilities = fetch_constants_cached('abilities', [
        "{$api_base}/constants/abilities.json",
        "{$api_base}/constants/abilities",
    ], $timeout, $constants_cache_ttl, false);
    $ability_ids = fetch_constants_cached('ability_ids', [
        "{$api_base}/constants/ability_ids.json",
        "{$api_base}/constants/ability_ids",
    ], $timeout, $constants_cache_ttl, false);

    [$match, $parsed] = normalize_match_response($match_response);
    if ($match === []) {
        throw new RuntimeException('Матч: в ответе API нет обязательного поля match.');
    }

    if (empty($match['players']) || !is_array($match['players'])) {
        throw new RuntimeException('Матч: в данных нет списка игроков.');
    }

    if (!empty($parsed['players']) && is_array($parsed['players'])) {
        $match['players'] = merge_parsed_player_data($match['players'], $parsed['players']);
    }

    // Surface parsed-only top-level collections (teamfights, chat, objectives,
    // pauses, cosmetics, draft_timings, gold/xp advantages) so views that
    // depend on them can render real data instead of empty placeholders.
    foreach (['teamfights', 'chat', 'objectives', 'pauses', 'cosmetics', 'draft_timings', 'radiant_gold_adv', 'radiant_xp_adv'] as $parsed_key) {
        if (!empty($parsed[$parsed_key]) && is_array($parsed[$parsed_key]) && empty($match[$parsed_key])) {
            $match[$parsed_key] = $parsed[$parsed_key];
        }
    }

    $items_by_id = normalize_items_by_id($items);
    [$radiant_players, $dire_players] = split_players_by_team($match['players']);
    $draft = build_draft($match['picks_bans'] ?? [], $heroes);

    $context = [
        'parse_status' => $parse_status,
        'match_id' => $match_id,
        'match' => $match,
        'heroes' => $heroes,
        'items' => $items,
        'items_by_id' => $items_by_id,
        'abilities' => $abilities,
        'ability_ids' => $ability_ids,
        'radiant_players' => $radiant_players,
        'dire_players' => $dire_players,
        'radiant_picks' => $draft['radiant_picks'],
        'radiant_bans' => $draft['radiant_bans'],
        'dire_picks' => $draft['dire_picks'],
        'dire_bans' => $draft['dire_bans'],
        'radiant_score' => sum_team_kills($radiant_players),
        'dire_score' => sum_team_kills($dire_players),
        'match_duration' => isset($match['duration']) ? gmdate('i:s', (int) $match['duration']) : '-',
        'match_end_time' => isset($match['start_time']) ? date('d.m.Y H:i', (int) $match['start_time']) : '-',
    ];

    if ($parse_status === 'parsed') {
        app_cache_set('match_context', $cache_key, $context);
    }

    return $context;
}

function normalize_match_response(array $match_response): array
{
    if (!empty($match_response['match']) && is_array($match_response['match'])) {
        return [$match_response['match'], is_array($match_response['parsed'] ?? null) ? $match_response['parsed'] : []];
    }

    if (!empty($match_response['match_id']) && !empty($match_response['players']) && is_array($match_response['players'])) {
        return [$match_response, ['players' => $match_response['players']]];
    }

    return [[], []];
}

function merge_parsed_player_data(array $players, array $parsed_players): array
{
    $parsed_by_slot = [];
    foreach ($parsed_players as $parsed_player) {
        if (!is_array($parsed_player) || !isset($parsed_player['player_slot'])) {
            continue;
        }

        $parsed_by_slot[(int) $parsed_player['player_slot']] = $parsed_player;
    }

    foreach ($players as $index => $player) {
        if (!is_array($player) || !isset($player['player_slot'])) {
            continue;
        }

        $slot = (int) $player['player_slot'];
        if (!isset($parsed_by_slot[$slot])) {
            continue;
        }

        foreach ($parsed_by_slot[$slot] as $key => $value) {
            if (!array_key_exists($key, $player)) {
                $player[$key] = $value;
            }
        }

        $players[$index] = $player;
    }

    return $players;
}

function normalize_items_by_id(array $items): array
{
    $items_by_id = [];

    foreach ($items as $key => $item) {
        if (!is_array($item)) {
            continue;
        }

        if (isset($item['id'])) {
            if (!isset($item['name'])) {
                $item['name'] = (string) $key;
            }
            $items_by_id[(int) $item['id']] = $item;
            continue;
        }

        if (is_numeric($key)) {
            $items_by_id[(int) $key] = $item;
        }
    }

    return $items_by_id;
}

function split_players_by_team(array $players): array
{
    $radiant_players = [];
    $dire_players = [];

    foreach ($players as $player) {
        if (!is_array($player)) {
            continue;
        }

        $is_radiant = isset($player['isRadiant'])
            ? (bool) $player['isRadiant']
            : ((int) ($player['player_slot'] ?? 0) < 128);

        if ($is_radiant) {
            $radiant_players[] = $player;
        } else {
            $dire_players[] = $player;
        }
    }

    return [$radiant_players, $dire_players];
}

function build_draft(array $picks_bans, array $heroes): array
{
    $draft = [
        'radiant_picks' => [],
        'radiant_bans' => [],
        'dire_picks' => [],
        'dire_bans' => [],
    ];

    foreach ($picks_bans as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $hero_id = (int) ($entry['hero_id'] ?? 0);
        $is_pick = (bool) ($entry['is_pick'] ?? false);
        $team = (int) ($entry['team'] ?? 0);
        $data = [
            'hero_id' => $hero_id,
            'hero_name' => get_hero_name($hero_id, $heroes),
            'order_label' => ($is_pick ? 'ПИК' : 'БАН') . ' ' . ((int) ($entry['order'] ?? 0) + 1),
        ];

        if ($team === 0) {
            $draft[$is_pick ? 'radiant_picks' : 'radiant_bans'][] = $data;
        } else {
            $draft[$is_pick ? 'dire_picks' : 'dire_bans'][] = $data;
        }
    }

    return $draft;
}

function sum_team_kills(array $players): int
{
    $score = 0;
    foreach ($players as $player) {
        $score += (int) ($player['kills'] ?? 0);
    }

    return $score;
}
