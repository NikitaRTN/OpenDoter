<?php

declare(strict_types=1);

function render_search_page(string $query, array $players, ?array $match): void
{
    ?>
    <section class="profile-panel">
        <div class="team-header">
            <div>
                <span class="team-title">Поиск</span>
                <span class="team-subtitle"> - игроки и матчи</span>
            </div>
        </div>

        <?php if ($query === ''): ?>
            <div class="empty-state">Введите ник игрока или Match ID в поиск сверху.</div>
        <?php endif; ?>

        <?php if ($match !== null): ?>
            <div class="search-match-result">
                <span>Найден матч #<?php echo e($match['match_id']); ?></span>
                <a class="profile-link" href="<?php echo e(match_url((string) $match['match_id'], 'overview')); ?>">Открыть обзор</a>
            </div>
        <?php endif; ?>

        <?php if ($players !== []): ?>
            <table class="overview-table search-table">
                <thead>
                    <tr>
                        <th>Игрок</th>
                        <th class="col-center">Account ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($players, 0, 20) as $player): ?>
                        <tr>
                            <td>
                                <div class="player-cell">
                                    <?php if (!empty($player['avatarfull'])): ?><img class="profile-mini-avatar" src="<?php echo e($player['avatarfull']); ?>" alt=""><?php endif; ?>
                                    <a class="player-name" href="<?php echo e(player_url($player['account_id'] ?? 0)); ?>"><?php echo e($player['personaname'] ?? 'Игрок'); ?></a>
                                </div>
                            </td>
                            <td class="col-center"><?php echo e($player['account_id'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($query !== '' && $match === null): ?>
            <div class="empty-state">Ничего не найдено.</div>
        <?php endif; ?>
    </section>
    <?php
}
