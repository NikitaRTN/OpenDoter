<?php

declare(strict_types=1);

/**
 * Build a short-name => item_id lookup from the items_by_id map so we can
 * resolve OpenDota purchase_log keys (which use item short names like "blink")
 * back to their icon/title via the existing get_item_* helpers.
 */
function items_name_to_id(array $items_by_id): array
{
    $map = [];
    foreach ($items_by_id as $id => $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = (string) ($item['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $map[$name] = (int) $id;
        $map[str_replace('item_', '', $name)] = (int) $id;
    }

    return $map;
}

function match_has_purchase_log(array $players): bool
{
    foreach ($players as $player) {
        if (is_array($player) && !empty($player['purchase_log']) && is_array($player['purchase_log'])) {
            return true;
        }
    }

    return false;
}

/**
 * Main entry point for the /items tab. Shows the current inventory (with
 * backpack + neutral + Aghanim effects) and, when the match is parsed, a
 * purchase timeline (build order with timings) for every player.
 */
function render_items_tab(array $radiant_players, array $dire_players, array $heroes, array $items_by_id): void
{
    $players = array_merge($radiant_players, $dire_players);

    render_match_tab_section(match_tab_title('items'), 'инвентарь, бэкпак и эффекты', static function () use ($players, $heroes, $items_by_id): void {
        render_items_inventory_table($players, $heroes, $items_by_id);
    });

    if (match_has_purchase_log($players)) {
        $name_to_id = items_name_to_id($items_by_id);
        render_match_tab_section('Сборка', 'тайминги покупок — порядок и время', static function () use ($players, $heroes, $items_by_id, $name_to_id): void {
            foreach ($players as $player) {
                render_purchase_timeline($player, $heroes, $items_by_id, $name_to_id);
            }
        });
    } else {
        render_match_tab_section('Сборка', 'тайминги покупок', static function (): void {
            render_match_tab_empty('Тайминги покупок недоступны: матч не распарсен.');
        });
    }
}

function render_items_inventory_table(array $players, array $heroes, array $items_by_id): void
{
    ?>
    <table class="overview-table">
        <thead><tr>
            <th>Игрок</th>
            <th class="col-center">Команда</th>
            <th>Инвентарь</th>
            <th>Бэкпак</th>
            <th class="col-center">Нейтр.</th>
            <th class="col-center">Aghanim</th>
        </tr></thead>
        <tbody>
        <?php foreach ($players as $player): ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <td><?php render_item_icons_row($player, $items_by_id, ['item_0', 'item_1', 'item_2', 'item_3', 'item_4', 'item_5']); ?></td>
                <td><?php render_item_icons_row($player, $items_by_id, ['backpack_0', 'backpack_1', 'backpack_2']); ?></td>
                <td class="col-center"><?php render_single_item_icon((int) ($player['item_neutral'] ?? 0), $items_by_id); ?></td>
                <td class="col-center"><?php render_aghanim_badges($player); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function render_item_icons_row(array $player, array $items_by_id, array $slots): void
{
    ?><div class="items-cell"><?php
    foreach ($slots as $slot) {
        render_single_item_icon((int) ($player[$slot] ?? 0), $items_by_id);
    }
    ?></div><?php
}

function render_single_item_icon(int $item_id, array $items_by_id): void
{
    $img = get_item_img($item_id, $items_by_id);
    $title = get_item_title($item_id, $items_by_id);
    ?>
    <div class="item-slot" title="<?php echo e($title); ?>">
        <?php if ($img): ?>
            <img src="<?php echo e($img); ?>" alt="<?php echo e($title); ?>">
        <?php elseif ($item_id > 0): ?>
            <span class="missing-item">!</span>
        <?php endif; ?>
    </div>
    <?php
}

function render_aghanim_badges(array $player): void
{
    ?>
    <span class="buff-icon <?php echo !empty($player['aghanims_scepter']) ? 'active' : ''; ?>" title="Aghanim Scepter (<?php echo !empty($player['aghanims_scepter']) ? 'Активен' : 'Отсутствует'; ?>)">S</span>
    <span class="buff-icon <?php echo !empty($player['aghanims_shard']) ? 'active' : ''; ?>" title="Aghanim Shard (<?php echo !empty($player['aghanims_shard']) ? 'Активен' : 'Отсутствует'; ?>)">D</span>
    <?php
}

function render_purchase_timeline(array $player, array $heroes, array $items_by_id, array $name_to_id): void
{
    $log = (!empty($player['purchase_log']) && is_array($player['purchase_log'])) ? $player['purchase_log'] : [];
    usort($log, static fn (array $a, array $b): int => ((int) ($a['time'] ?? 0)) <=> ((int) ($b['time'] ?? 0)));
    $hero_img = get_hero_img((int) ($player['hero_id'] ?? 0), $heroes);
    $name = (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним');
    ?>
    <div class="build-row">
        <div class="build-player">
            <?php if ($hero_img): ?><img class="hero-img" src="<?php echo e($hero_img); ?>" alt=""><?php endif; ?>
            <span class="player-name <?php echo e(player_match_team_class($player)); ?>"><?php echo e($name); ?></span>
        </div>
        <?php if ($log === []): ?>
            <div class="build-empty">Нет данных о покупках</div>
        <?php else: ?>
            <div class="build-timeline">
                <?php foreach ($log as $entry): ?>
                    <?php
                    $key = (string) ($entry['key'] ?? '');
                    $item_id = $name_to_id[$key] ?? ($name_to_id['item_' . $key] ?? 0);
                    $img = get_item_img((int) $item_id, $items_by_id);
                    $title = $item_id > 0 ? get_item_title((int) $item_id, $items_by_id) : ucwords(str_replace('_', ' ', $key));
                    $time_label = format_match_tab_time((int) ($entry['time'] ?? 0));
                    ?>
                    <div class="build-item" title="<?php echo e($title . ' — ' . $time_label); ?>">
                        <?php if ($img): ?>
                            <img src="<?php echo e($img); ?>" alt="<?php echo e($title); ?>">
                        <?php else: ?>
                            <span class="missing-item">?</span>
                        <?php endif; ?>
                        <span class="build-time"><?php echo e($time_label); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Legacy renderer kept for backward compatibility (previously the only items
 * view). New code should use render_items_tab().
 */
function render_items_match_tab(array $players, array $heroes, array $items_by_id): void
{
    render_items_inventory_table($players, $heroes, $items_by_id);
}
