<?php
declare(strict_types=1);

function render_team_overview(
    string $team_name,
    string $team_class,
    array $players,
    bool $is_winner,
    array $heroes,
    array $items_by_id
): void {
    ?>
    <div class="team-header">
        <div>
            <span class="team-title <?php echo e($team_class); ?>"><?php echo e($team_name); ?></span>
            <span class="team-subtitle"> - Обзор</span>
        </div>
        <?php if ($is_winner): ?>
            <span class="winner-label">Победитель</span>
        <?php endif; ?>
    </div>

    <table class="overview-table">
        <?php render_overview_table_head(); ?>
        <tbody>
            <?php render_player_rows($players, $heroes, $items_by_id); ?>
            <?php render_totals_row($players); ?>
        </tbody>
    </table>
    <?php
}

function render_overview_table_head(): void
{
    ?>
    <thead>
        <tr>
            <th class="player-column" title="Имя игрока, его числовой ранг и иконка героя">Игрок</th>
            <th class="col-center level-column" title="Уровень героя">Ур.</th>
            <th class="col-center small-column" title="Убийства (Kills)">У</th>
            <th class="col-center small-column" title="Смерти (Deaths)">С</th>
            <th class="col-center small-column" title="Помощь (Assists)">П</th>
            <th class="col-center creeps-column" title="Добитые крипы / Набитые союзные крипы (Last Hits / Denies)">ДК / НО</th>
            <th class="col-center gold-column" title="Общая ценность игрока (Net Worth)">Общая Ценность</th>
            <th class="col-center rate-column" title="Заработанное золото в минуту (GPM) / Опыт в минуту (XPM)">З/М / О/М</th>
            <th class="col-center stat-column" title="Урон по вражеским героям (Hero Damage)">Урон</th>
            <th class="col-center stat-column" title="Урон по вражеским постройкам (Tower Damage)">Пстр</th>
            <th class="col-center stat-column" title="Лечение союзных героев (Hero Healing)">Леч</th>
            <th title="Предметы в инвентаре игрока">Предметы</th>
            <th class="col-center effects-column" title="Наличие Aghanim Scepter (S) и Aghanim Shard (D)">Эффекты</th>
        </tr>
    </thead>
    <?php
}

function render_player_rows(array $players, array $heroes, array $items_by_id): void
{
    foreach ($players as $player) {
        $stats = get_player_stats($player);
        $player_name = $player['personaname'] ?? $player['name'] ?? 'Аноним';
        $hero_img = get_hero_img((int) ($player['hero_id'] ?? 0), $heroes);
        ?>
        <tr>
            <td>
                <div class="player-cell">
                    <?php if ($hero_img): ?>
                        <img class="hero-img" src="<?php echo e($hero_img); ?>" alt="">
                    <?php else: ?>
                        <span class="missing-asset">Нет героя #<?php echo e($player['hero_id'] ?? 0); ?></span>
                    <?php endif; ?>
                    <div class="player-info">
                        <?php if (!empty($player['account_id'])): ?>
                            <a class="player-name" href="<?php echo e(player_url($player['account_id'])); ?>"><?php echo e($player_name); ?></a>
                        <?php else: ?>
                            <span class="player-name"><?php echo e($player_name); ?></span>
                        <?php endif; ?>
                        <span class="player-rank"><?php echo e(get_rank_title($player['rank_tier'] ?? 0)); ?></span>
                    </div>
                </div>
            </td>
            <td class="col-center"><?php echo e($player['level'] ?? 0); ?></td>
            <td class="col-center col-kills"><?php echo e($stats['kills']); ?></td>
            <td class="col-center col-deaths"><?php echo e($stats['deaths']); ?></td>
            <td class="col-center col-assists"><?php echo e($stats['assists']); ?></td>
            <td class="col-center" title="Добито: <?php echo e($stats['last_hits']); ?>, Денаев: <?php echo e($stats['denies']); ?>"><?php echo e($stats['last_hits']); ?>/<?php echo e($stats['denies']); ?></td>
            <td class="col-center col-gold" title="Точное значение: <?php echo e(number_format($stats['total_gold'])); ?>"><?php echo e(format_stat($stats['total_gold'])); ?></td>
            <td class="col-center" title="Золота в минуту: <?php echo e($stats['gold_per_min']); ?> / Опыта в минуту: <?php echo e($stats['xp_per_min']); ?>"><?php echo e($stats['gold_per_min']); ?> / <?php echo e($stats['xp_per_min']); ?></td>
            <td class="col-center" title="Точное значение: <?php echo e(number_format($stats['hero_damage'])); ?>"><?php echo e(format_stat($stats['hero_damage'])); ?></td>
            <td class="col-center" title="Точное значение: <?php echo e(number_format($stats['tower_damage'])); ?>"><?php echo e(format_stat($stats['tower_damage'])); ?></td>
            <td class="col-center" title="Точное значение: <?php echo e(number_format($stats['hero_healing'])); ?>"><?php echo e(format_stat($stats['hero_healing'])); ?></td>
            <td><?php render_items_cell($player, $items_by_id); ?></td>
            <td class="col-center">
                <span class="buff-icon <?php echo !empty($player['aghanims_scepter']) ? 'active' : ''; ?>" title="Aghanim Scepter (<?php echo !empty($player['aghanims_scepter']) ? 'Активен' : 'Отсутствует'; ?>)">S</span>
                <span class="buff-icon <?php echo !empty($player['aghanims_shard']) ? 'active' : ''; ?>" title="Aghanim Shard (<?php echo !empty($player['aghanims_shard']) ? 'Активен' : 'Отсутствует'; ?>)">D</span>
            </td>
        </tr>
        <?php
    }
}

function render_items_cell(array $player, array $items_by_id): void
{
    ?>
    <div class="items-cell">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <?php
            $item_id = (int) ($player["item_{$i}"] ?? 0);
            $img_src = get_item_img($item_id, $items_by_id);
            $item_title = get_item_title($item_id, $items_by_id);
            ?>
            <div class="item-slot" title="<?php echo e($item_title); ?>">
                <?php if ($img_src): ?>
                    <img src="<?php echo e($img_src); ?>" alt="<?php echo e($item_title); ?>">
                <?php elseif ($item_id > 0): ?>
                    <span class="missing-item">!</span>
                <?php endif; ?>
            </div>
        <?php endfor; ?>

        <?php
        $neutral_id = (int) ($player['item_neutral'] ?? 0);
    $neutral_src = get_item_img($neutral_id, $items_by_id);
    $neutral_title = get_item_title($neutral_id, $items_by_id);
    ?>
        <div class="item-neutral" title="Нейтральный предмет: <?php echo e($neutral_title); ?>">
            <?php if ($neutral_src): ?>
                <img src="<?php echo e($neutral_src); ?>" alt="<?php echo e($neutral_title); ?>">
            <?php elseif ($neutral_id > 0): ?>
                <span class="missing-item">!</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function render_totals_row(array $players): void
{
    $totals = [
        'kills' => 0,
        'deaths' => 0,
        'assists' => 0,
        'last_hits' => 0,
        'denies' => 0,
        'total_gold' => 0,
        'gold_per_min' => 0,
        'xp_per_min' => 0,
        'hero_damage' => 0,
        'tower_damage' => 0,
        'hero_healing' => 0,
    ];

    foreach ($players as $player) {
        $stats = get_player_stats($player);
        foreach ($totals as $key => $value) {
            $totals[$key] += $stats[$key];
        }
    }
    ?>
    <tr class="totals-row">
        <td>Всего</td>
        <td class="col-center">-</td>
        <td class="col-center"><?php echo e($totals['kills']); ?></td>
        <td class="col-center"><?php echo e($totals['deaths']); ?></td>
        <td class="col-center"><?php echo e($totals['assists']); ?></td>
        <td class="col-center" title="Суммарно крипов (убито/добыто)"><?php echo e($totals['last_hits']); ?>/<?php echo e($totals['denies']); ?></td>
        <td class="col-center col-gold" title="Суммарная ценность команды: <?php echo e(number_format($totals['total_gold'])); ?>"><?php echo e(format_stat($totals['total_gold'])); ?></td>
        <td class="col-center" title="Суммарные GPM / XPM команды"><?php echo e(format_stat($totals['gold_per_min'])); ?> / <?php echo e(format_stat($totals['xp_per_min'])); ?></td>
        <td class="col-center" title="Суммарный урон по героям: <?php echo e(number_format($totals['hero_damage'])); ?>"><?php echo e(format_stat($totals['hero_damage'])); ?></td>
        <td class="col-center" title="Суммарный урон по постройкам: <?php echo e(number_format($totals['tower_damage'])); ?>"><?php echo e(format_stat($totals['tower_damage'])); ?></td>
        <td class="col-center" title="Суммарное лечение: <?php echo e(number_format($totals['hero_healing'])); ?>"><?php echo e(format_stat($totals['hero_healing'])); ?></td>
        <td colspan="2"></td>
    </tr>
    <?php
}

function render_draft(string $title, string $team_class, array $picks, array $bans, array $heroes): void
{
    if ($picks === [] && $bans === []) {
        return;
    }
    ?>
    <div class="draft-container-single">
        <div class="draft-team-header <?php echo e($team_class); ?>"><?php echo e($title); ?></div>
        <?php render_draft_row('Пики:', 'pick', $picks, $heroes); ?>
        <?php render_draft_row('Баны:', 'ban', $bans, $heroes); ?>
    </div>
    <?php
}

function render_draft_row(string $label, string $type, array $entries, array $heroes): void
{
    ?>
    <div class="draft-row">
        <div class="draft-row-label"><?php echo e($label); ?></div>
        <div class="pb-list">
            <?php foreach ($entries as $entry): ?>
                <?php $img_src = get_hero_img((int) $entry['hero_id'], $heroes); ?>
                <div class="pb-item <?php echo e($type); ?>" title="<?php echo e($entry['order_label'] . ' - ' . $entry['hero_name']); ?>">
                    <?php if ($img_src): ?>
                        <img src="<?php echo e($img_src); ?>" alt="">
                    <?php else: ?>
                        <span class="missing-item">!</span>
                    <?php endif; ?>
                    <div class="pb-badge"><?php echo e($entry['order_label']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function get_player_stats(array $player): array
{
    return [
        'kills' => (int) ($player['kills'] ?? 0),
        'deaths' => (int) ($player['deaths'] ?? 0),
        'assists' => (int) ($player['assists'] ?? 0),
        'last_hits' => (int) ($player['last_hits'] ?? 0),
        'denies' => (int) ($player['denies'] ?? 0),
        'total_gold' => (int) ($player['total_gold'] ?? 0),
        'gold_per_min' => (int) ($player['gold_per_min'] ?? 0),
        'xp_per_min' => (int) ($player['xp_per_min'] ?? 0),
        'hero_damage' => (int) ($player['hero_damage'] ?? 0),
        'tower_damage' => (int) ($player['tower_damage'] ?? 0),
        'hero_healing' => (int) ($player['hero_healing'] ?? 0),
    ];
}
