<?php

declare(strict_types=1);

function load_match_context(array $config, ?string $requested_match_id = null): array
{
    $api_base = rtrim((string) $config['api_base'], '/');
    $public_api_base = rtrim((string) $config['public_api_base'], '/');
    $match_id = $requested_match_id ?: (string) $config['match_id'];
    $timeout = (float) $config['request_timeout'];

    $match_response = fetch_first_required_json([
        "{$api_base}/api/match/{$match_id}?full=1",
        "{$public_api_base}/matches/{$match_id}",
    ], $timeout, 'Матч');
    $heroes = fetch_first_required_json([
        "{$api_base}/constants/heroes.json",
        "{$public_api_base}/constants/heroes",
    ], $timeout, 'Герои');
    $items = fetch_first_required_json([
        "{$api_base}/constants/items.json",
        "{$public_api_base}/constants/items",
    ], $timeout, 'Предметы');
    $abilities = fetch_first_optional_json([
        "{$api_base}/constants/abilities.json",
        "{$public_api_base}/constants/abilities",
    ], $timeout);
    $ability_ids = fetch_first_optional_json([
        "{$api_base}/constants/ability_ids.json",
        "{$public_api_base}/constants/ability_ids",
    ], $timeout);

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

    $items_by_id = normalize_items_by_id($items);
    [$radiant_players, $dire_players] = split_players_by_team($match['players']);
    $draft = build_draft($match['picks_bans'] ?? [], $heroes);

    return [
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
