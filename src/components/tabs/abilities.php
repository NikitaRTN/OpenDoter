<?php

declare(strict_types=1);

/**
 * Skill build tab. Renders the level-by-level ability order with real icons
 * (loaded from OpenDota ability constants) instead of raw numeric ids.
 */
function render_abilities_match_tab(array $players, array $heroes, array $abilities = [], array $ability_ids = []): void
{
    $has_any = false;
    foreach ($players as $player) {
        if (!empty($player['ability_upgrades_arr']) && is_array($player['ability_upgrades_arr'])) {
            $has_any = true;
            break;
        }
    }

    if (!$has_any) {
        render_match_tab_empty('В ответе API нет подробной прокачки способностей для этого матча.');
        return;
    }

    $constants_ok = $abilities !== [] && $ability_ids !== [];
    ?>
    <div class="ability-build-wrap">
        <?php if (!$constants_ok): ?>
            <div class="ability-note">Константы способностей недоступны — показаны числовые ID. Запустите локальный API с /constants/abilities, чтобы увидеть иконки.</div>
        <?php endif; ?>
        <?php foreach ($players as $player): ?>
            <?php
            $ups = array_values(array_filter((array) ($player['ability_upgrades_arr'] ?? []), 'is_numeric'));
            if ($ups === []) {
                continue;
            }
            $hero_img = get_hero_img((int) ($player['hero_id'] ?? 0), $heroes);
            $player_name = (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним');
            ?>
            <div class="ability-build-card">
                <div class="ability-build-hero">
                    <?php if ($hero_img): ?><img class="hero-img" src="<?php echo e($hero_img); ?>" alt=""><?php endif; ?>
                    <div class="ability-build-meta">
                        <?php if (!empty($player['account_id'])): ?>
                            <a class="player-name" href="<?php echo e(player_url($player['account_id'])); ?>"><?php echo e($player_name); ?></a>
                        <?php else: ?>
                            <span class="player-name"><?php echo e($player_name); ?></span>
                        <?php endif; ?>
                        <span class="ability-build-sub"><?php echo e(get_hero_name((int) ($player['hero_id'] ?? 0), $heroes)); ?> · <?php echo e(player_match_team_label($player)); ?></span>
                    </div>
                </div>
                <div class="ability-build-track">
                    <?php foreach ($ups as $i => $raw_aid): ?>
                        <?php
                        $aid = (int) $raw_aid;
                        $level = $i + 1;
                        $img = $constants_ok ? get_ability_img($aid, $abilities, $ability_ids) : null;
                        $is_talent = $constants_ok ? is_talent_ability($aid, $abilities, $ability_ids) : false;
                        $aname = $constants_ok ? get_ability_name($aid, $abilities, $ability_ids) : ('Способность #' . $aid);
                        $cell_class = 'ability-cell' . ($is_talent ? ' talent' : '');
                        ?>
                        <div class="<?php echo $cell_class; ?>" title="Уровень <?php echo $level; ?>: <?php echo e($aname); ?>">
                            <span class="ability-level"><?php echo $level; ?></span>
                            <?php if ($img): ?>
                                <img class="ability-icon" src="<?php echo e($img); ?>" alt="" loading="lazy">
                            <?php elseif ($is_talent): ?>
                                <span class="ability-talent">★</span>
                            <?php else: ?>
                                <span class="ability-fallback"><?php echo e((string) $aid); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
