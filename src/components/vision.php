<?php

declare(strict_types=1);

function render_vision_page(
    array $match,
    array $radiant_players,
    array $dire_players,
    array $heroes,
    array $items_by_id
): void {
    $wards = build_vision_wards($radiant_players, $dire_players, $heroes);
    $vision_players = build_vision_players($wards);
    $duration = max(0, (int) ($match['duration'] ?? 0));
    $match_id = (string) ($match['match_id'] ?? '');
    $observer_img = get_item_img(42, $items_by_id);
    $sentry_img = get_item_img(43, $items_by_id);
    ?>
    <?php render_detailed_stats_gate($match_id, 'статистики вижена', count($wards) > 0); ?>
    <div data-stats-content <?php echo count($wards) > 0 ? '' : 'hidden'; ?>>
    <section class="vision-layout" data-vision-root>
        <div class="vision-map-panel">
            <div class="vision-map-toolbar">
                <div>
                    <div class="vision-title">Вижен</div>
                    <div class="vision-subtitle" data-vision-summary><?php echo e(count($wards)); ?> установок за матч</div>
                </div>
                <div class="vision-legend" aria-label="Легенда">
                    <span class="legend-item"><span class="legend-dot observer"></span> Observer</span>
                    <span class="legend-item"><span class="legend-dot sentry"></span> Sentry</span>
                    <span class="legend-item"><span class="legend-dot inactive"></span> Пропал</span>
                </div>
            </div>

            <div class="vision-map-wrap">
                <div class="vision-map" data-vision-map>
                    <?php foreach ($wards as $ward): ?>
                        <button
                            class="ward-marker <?php echo e($ward['team']); ?> <?php echo e($ward['kind']); ?>"
                            type="button"
                            style="left: <?php echo e($ward['left']); ?>%; top: <?php echo e($ward['top']); ?>%;"
                            data-ward-id="<?php echo e($ward['id']); ?>"
                            aria-label="<?php echo e($ward['label']); ?>"
                            title="<?php echo e($ward['label']); ?>"
                        >
                            <span class="ward-ring"></span>
                            <img class="ward-eye" src="<?php echo e($ward['icon']); ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <aside class="vision-controls">
            <div class="vision-time-card">
                <div class="vision-time-header">
                    <span data-time-title>За все время</span>
                    <strong data-active-count><?php echo e(count($wards)); ?></strong>
                </div>
                <input class="vision-slider" type="range" min="0" max="<?php echo e($duration); ?>" value="0" step="1" data-time-slider>
                <div class="vision-ticks" aria-hidden="true">
                    <span>Все</span>
                    <span><?php echo e(format_vision_time((int) floor($duration / 3))); ?></span>
                    <span><?php echo e(format_vision_time((int) floor($duration * 2 / 3))); ?></span>
                    <span><?php echo e(format_vision_time($duration)); ?></span>
                </div>
            </div>

            <div class="vision-filter-grid">
                <label class="vision-toggle radiant">
                    <input type="checkbox" data-filter-team="radiant" checked>
                    <span>Свет</span>
                </label>
                <label class="vision-toggle dire">
                    <input type="checkbox" data-filter-team="dire" checked>
                    <span>Тьма</span>
                </label>
                <label class="vision-toggle">
                    <input type="checkbox" data-filter-kind="observer" checked>
                    <?php if ($observer_img): ?><img src="<?php echo e($observer_img); ?>" alt=""><?php endif; ?>
                    <span>Observer</span>
                </label>
                <label class="vision-toggle">
                    <input type="checkbox" data-filter-kind="sentry" checked>
                    <?php if ($sentry_img): ?><img src="<?php echo e($sentry_img); ?>" alt=""><?php endif; ?>
                    <span>Sentry</span>
                </label>
            </div>

            <div class="vision-stats">
                <div><span>Поставлено</span><strong data-stat-total><?php echo e(count($wards)); ?></strong></div>
                <div><span>Сломано</span><strong data-stat-killed>0</strong></div>
                <div><span>Истекло</span><strong data-stat-expired>0</strong></div>
            </div>

            <div class="vision-player-filter">
                <div class="vision-control-title">Игроки</div>
                <div class="vision-player-list">
                    <?php foreach ($vision_players as $player): ?>
                        <label class="vision-player-toggle <?php echo e($player['team']); ?>">
                            <input type="checkbox" data-filter-player="<?php echo e($player['key']); ?>" checked>
                            <?php if ($player['hero_img']): ?><img src="<?php echo e($player['hero_img']); ?>" alt=""><?php endif; ?>
                            <span><?php echo e($player['owner']); ?></span>
                            <strong><?php echo e($player['total']); ?></strong>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </section>

    <section class="vision-log-section">
        <div class="team-header">
            <div>
                <span class="team-title">Журнал вардов</span>
                <span class="team-subtitle"> - постановка, исчезновение и владелец</span>
            </div>
        </div>

        <table class="overview-table vision-log-table">
            <thead>
                <tr>
                    <th>Тип</th>
                    <th>Владелец</th>
                    <th class="col-center">Поставлен</th>
                    <th class="col-center">Исчез</th>
                    <th class="col-center">Длительность</th>
                    <th>Статус</th>
                    <th class="col-center">Координаты</th>
                </tr>
            </thead>
            <tbody data-vision-log>
                <?php foreach ($wards as $ward): ?>
                    <tr data-ward-row="<?php echo e($ward['id']); ?>">
                        <td>
                            <span class="ward-type-cell <?php echo e($ward['kind']); ?>">
                                <?php echo e($ward['kind'] === 'observer' ? 'Observer' : 'Sentry'); ?>
                            </span>
                        </td>
                        <td>
                            <div class="player-cell compact">
                                <?php if ($ward['hero_img']): ?><img class="hero-img" src="<?php echo e($ward['hero_img']); ?>" alt=""><?php endif; ?>
                                <div class="player-info">
                                    <?php if (!empty($ward['account_id'])): ?>
                                        <a class="player-name" href="<?php echo e(player_url($ward['account_id'])); ?>"><?php echo e($ward['owner']); ?></a>
                                    <?php else: ?>
                                        <span class="player-name"><?php echo e($ward['owner']); ?></span>
                                    <?php endif; ?>
                                    <span class="<?php echo e($ward['team'] === 'radiant' ? 'radiant-title' : 'dire-title'); ?>">
                                        <?php echo e($ward['team'] === 'radiant' ? 'Свет' : 'Тьма'); ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="col-center"><?php echo e(format_vision_time($ward['placed'])); ?></td>
                        <td class="col-center"><?php echo e($ward['removed'] !== null ? format_vision_time($ward['removed']) : '-'); ?></td>
                        <td class="col-center"><?php echo e(format_vision_time($ward['duration'])); ?></td>
                        <td><?php echo e($ward['status_label']); ?></td>
                        <td class="col-center"><?php echo e($ward['coords']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    </div>

    <script>
        window.visionWards = <?php echo json_encode($wards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.visionDuration = <?php echo json_encode($duration); ?>;
    </script>
    <script src="<?php echo e(asset_url('src/assets/vision.js')); ?>"></script>
    <?php
}

function build_vision_wards(array $radiant_players, array $dire_players, array $heroes): array
{
    $wards = [];
    foreach ([['radiant', $radiant_players], ['dire', $dire_players]] as [$team, $players]) {
        foreach ($players as $player) {
            $wards = array_merge($wards, collect_player_wards($player, $team, 'observer', 'obs_log', 'obs_left_log', $heroes));
            $wards = array_merge($wards, collect_player_wards($player, $team, 'sentry', 'sen_log', 'sen_left_log', $heroes));
        }
    }

    usort($wards, static fn (array $a, array $b): int => $a['placed'] <=> $b['placed']);
    foreach ($wards as $index => $ward) {
        $wards[$index]['id'] = 'ward-' . ($index + 1);
    }

    return $wards;
}

function build_vision_players(array $wards): array
{
    $players = [];
    foreach ($wards as $ward) {
        $key = (string) $ward['owner_key'];
        if (!isset($players[$key])) {
            $players[$key] = [
                'key' => $key,
                'owner' => $ward['owner'],
                'team' => $ward['team'],
                'hero_img' => $ward['hero_img'],
                'total' => 0,
            ];
        }

        $players[$key]['total']++;
    }

    usort($players, static function (array $a, array $b): int {
        if ($a['team'] !== $b['team']) {
            return $a['team'] === 'radiant' ? -1 : 1;
        }

        return $b['total'] <=> $a['total'];
    });

    return $players;
}

function collect_player_wards(array $player, string $team, string $kind, string $place_key, string $left_key, array $heroes): array
{
    $placements = isset($player[$place_key]) && is_array($player[$place_key]) ? $player[$place_key] : [];
    $left_logs = isset($player[$left_key]) && is_array($player[$left_key]) ? $player[$left_key] : [];
    $left_by_handle = [];
    $left_by_key = [];

    foreach ($left_logs as $left) {
        if (!is_array($left)) {
            continue;
        }
        if (isset($left['ehandle'])) {
            $left_by_handle[(string) $left['ehandle']] = $left;
        }
        if (isset($left['key'])) {
            $left_by_key[(string) $left['key']] = $left;
        }
    }

    $owner = (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним');
    $owner_key = 'slot-' . (int) ($player['player_slot'] ?? -1);
    $account_id = (int) ($player['account_id'] ?? 0);
    $hero_id = (int) ($player['hero_id'] ?? 0);
    $result = [];

    foreach ($placements as $placement) {
        if (!is_array($placement) || !isset($placement['x'], $placement['y'], $placement['time'])) {
            continue;
        }

        $left = null;
        if (isset($placement['ehandle'], $left_by_handle[(string) $placement['ehandle']])) {
            $left = $left_by_handle[(string) $placement['ehandle']];
        } elseif (isset($placement['key'], $left_by_key[(string) $placement['key']])) {
            $left = $left_by_key[(string) $placement['key']];
        }

        $placed = (int) $placement['time'];
        $removed = isset($left['time']) ? (int) $left['time'] : null;
        $duration = max(0, ($removed ?? $placed) - $placed);
        $status = get_ward_status($kind, $left, $duration);
        $x = (float) $placement['x'];
        $y = (float) $placement['y'];

        $result[] = [
            'id' => '',
            'team' => $team,
            'kind' => $kind,
            'owner' => $owner,
            'owner_key' => $owner_key,
            'account_id' => $account_id,
            'hero_img' => get_hero_img($hero_id, $heroes),
            'icon' => get_ward_icon($team, $kind),
            'placed' => $placed,
            'removed' => $removed,
            'duration' => $duration,
            'status' => $status,
            'status_label' => ward_status_label($status),
            'x' => $x,
            'y' => $y,
            'left' => clamp_percent(($x - 64) / 128 * 100),
            'top' => clamp_percent((192 - $y) / 128 * 100),
            'coords' => '[' . round($x, 1) . ', ' . round($y, 1) . ']',
            'label' => ($kind === 'observer' ? 'Observer' : 'Sentry') . ': ' . $owner . ', ' . format_vision_time($placed),
        ];
    }

    return $result;
}

function get_ward_icon(string $team, string $kind): string
{
    $prefix = $team === 'radiant' ? 'goodguys' : 'badguys';
    $suffix = $kind === 'observer' ? 'observer' : 'sentry';

    return asset_url("assets/vision/{$prefix}_{$suffix}.png");
}

function get_ward_status(string $kind, ?array $left, int $duration): string
{
    if ($left === null) {
        return 'active';
    }

    $natural_lifetime = $kind === 'observer' ? 360 : 420;
    if ($duration >= $natural_lifetime - 5) {
        return 'expired';
    }

    return empty($left['attackername']) ? 'expired' : 'killed';
}

function ward_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Стоял до конца данных',
        'killed' => 'Сломан',
        default => 'Истек',
    };
}

function clamp_percent(float $value): float
{
    return round(max(0, min(100, $value)), 3);
}

function format_vision_time(int $seconds): string
{
    $sign = $seconds < 0 ? '-' : '';
    $seconds = abs($seconds);

    return $sign . floor($seconds / 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}
