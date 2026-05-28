<?php

declare(strict_types=1);

function render_player_profile(array $profile, array $matches, array $heroes): void
{
    $data = is_array($profile['profile'] ?? null) ? $profile['profile'] : [];
    $name = (string) ($data['personaname'] ?? 'Игрок');
    $avatar = (string) ($data['avatarfull'] ?? $data['avatarmedium'] ?? '');
    ?>
    <section class="profile-panel">
        <div class="profile-main">
            <?php if ($avatar !== ''): ?><img class="profile-avatar" src="<?php echo e($avatar); ?>" alt=""><?php endif; ?>
            <div>
                <h1><?php echo e($name); ?></h1>
                <div class="profile-meta">
                    <span>Account ID: <?php echo e($data['account_id'] ?? '-'); ?></span>
                    <span><?php echo e(get_rank_title($profile['rank_tier'] ?? 0)); ?></span>
                    <?php if (!empty($profile['leaderboard_rank'])): ?><span>Leaderboard: <?php echo e($profile['leaderboard_rank']); ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!empty($data['profileurl'])): ?>
            <a class="profile-link" href="<?php echo e($data['profileurl']); ?>" target="_blank" rel="noreferrer">Steam</a>
        <?php endif; ?>
    </section>

    <section class="profile-panel">
        <div class="team-header">
            <div>
                <span class="team-title">Матчи игрока</span>
                <span class="team-subtitle"> - выберите матч для просмотра</span>
            </div>
        </div>
        <table class="overview-table profile-matches-table">
            <thead>
                <tr>
                    <th>Матч</th>
                    <th>Герой</th>
                    <th class="col-center">Результат</th>
                    <th class="col-center">КДА</th>
                    <th class="col-center">Длительность</th>
                    <th class="col-center">Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $match): ?>
                    <?php
                    $match_id = (string) ($match['match_id'] ?? '');
                    $hero_id = (int) ($match['hero_id'] ?? 0);
                    $is_radiant = (int) ($match['player_slot'] ?? 0) < 128;
                    $won = ((bool) ($match['radiant_win'] ?? false)) === $is_radiant;
                    ?>
                    <tr>
                        <td><a class="player-name" href="<?php echo e(match_url($match_id, 'overview')); ?>"><?php echo e($match_id); ?></a></td>
                        <td>
                            <div class="player-cell compact">
                                <?php $hero_img = get_hero_img($hero_id, $heroes); ?>
                                <?php if ($hero_img): ?><img class="hero-img" src="<?php echo e($hero_img); ?>" alt=""><?php endif; ?>
                                <span><?php echo e(get_hero_name($hero_id, $heroes)); ?></span>
                            </div>
                        </td>
                        <td class="col-center <?php echo $won ? 'radiant-title' : 'dire-title'; ?>"><?php echo $won ? 'Победа' : 'Поражение'; ?></td>
                        <td class="col-center"><?php echo e((int) ($match['kills'] ?? 0)); ?>/<?php echo e((int) ($match['deaths'] ?? 0)); ?>/<?php echo e((int) ($match['assists'] ?? 0)); ?></td>
                        <td class="col-center"><?php echo e(format_vision_time((int) ($match['duration'] ?? 0))); ?></td>
                        <td class="col-center"><?php echo !empty($match['start_time']) ? e(date('d.m.Y', (int) $match['start_time'])) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
}
