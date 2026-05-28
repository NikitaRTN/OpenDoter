<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_stat(mixed $value): string
{
    if ($value === null || $value === 0 || $value === '0' || $value === '-') {
        return '-';
    }

    $number = (float) $value;
    if ($number >= 1000) {
        return number_format($number / 1000, 1, '.', '') . 'k';
    }

    return (string) $value;
}

function get_rank_title(mixed $tier): string
{
    $tier = (int) $tier;
    if ($tier <= 0) {
        return 'Не откалиброван';
    }

    $ranks = [
        1 => 'Рекрут',
        2 => 'Страж',
        3 => 'Рыцарь',
        4 => 'Герой',
        5 => 'Легенда',
        6 => 'Властелин',
        7 => 'Божество',
        8 => 'Титан',
    ];

    $major = intdiv($tier, 10);
    $minor = $tier % 10;

    if ($major >= 8) {
        return 'Титан';
    }

    return ($ranks[$major] ?? 'Ранг') . " [$minor]";
}

function get_hero_img(int $hero_id, array $heroes): ?string
{
    if (!isset($heroes[$hero_id]) || empty($heroes[$hero_id]['name'])) {
        return null;
    }

    $system_name = str_replace('npc_dota_hero_', '', (string) $heroes[$hero_id]['name']);
    return "https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/heroes/{$system_name}.png";
}

function get_hero_name(int $hero_id, array $heroes): string
{
    if (!isset($heroes[$hero_id])) {
        return 'Неизвестный герой';
    }

    return (string) ($heroes[$hero_id]['localized_name'] ?? $heroes[$hero_id]['name'] ?? 'Неизвестный герой');
}

function get_item_img(int $item_id, array $items_by_id): ?string
{
    if ($item_id <= 0 || !isset($items_by_id[$item_id])) {
        return null;
    }

    $item = $items_by_id[$item_id];
    if (!empty($item['img'])) {
        $img = (string) $item['img'];
        if (str_starts_with($img, 'http')) {
            return $img;
        }

        return 'https://cdn.cloudflare.steamstatic.com' . explode('?', $img)[0];
    }

    $system_name = str_replace('item_', '', (string) ($item['name'] ?? ''));
    if ($system_name === '') {
        return null;
    }

    return "https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/items/{$system_name}.png";
}

function get_item_title(int $item_id, array $items_by_id): string
{
    if ($item_id <= 0) {
        return 'Пустой слот';
    }

    if (!isset($items_by_id[$item_id])) {
        return "Неизвестный предмет #{$item_id}";
    }

    if (!empty($items_by_id[$item_id]['dname'])) {
        return (string) $items_by_id[$item_id]['dname'];
    }

    $system_name = str_replace('item_', '', (string) ($items_by_id[$item_id]['name'] ?? 'item'));
    return ucwords(str_replace('_', ' ', $system_name));
}

function app_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return $path === '/' ? '/' : $path;
}

function match_url(string $match_id, string $tab = 'overview'): string
{
    return app_url("matches/{$match_id}/{$tab}");
}

function player_url(mixed $account_id): string
{
    return app_url('players/' . (int) $account_id);
}

function asset_url(string $path): string
{
    return app_url($path);
}
