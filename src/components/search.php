<?php

declare(strict_types=1);

function render_search_page(string $query, array $players, ?array $match): void
{
    ?>
    <section class="mb-5 rounded-lg border border-line bg-panel p-4">
        <div class="mb-3 flex items-center justify-between border-b border-line pb-1.5">
            <div>
                <span class="text-base font-bold uppercase tracking-wide text-main">Поиск</span>
                <span class="text-muted"> - игроки и матчи</span>
            </div>
        </div>

        <?php if ($query === ''): ?>
            <div class="mb-3.5 rounded-lg border border-line bg-black/15 p-3">Введите ник игрока или Match ID в поиск сверху.</div>
        <?php endif; ?>

        <?php if ($match !== null): ?>
            <div class="mb-3.5 flex items-center justify-between rounded-lg border border-line bg-black/15 p-3">
                <span>Найден матч #<?php echo e($match['match_id']); ?></span>
                <a class="inline-block rounded border border-link bg-link/10 px-4 py-2 text-xs font-bold uppercase text-link transition-colors hover:bg-link/20 hover:text-link-hover" href="<?php echo e(match_url((string) $match['match_id'], 'overview')); ?>">Открыть обзор</a>
            </div>
        <?php endif; ?>

        <?php if ($players !== []): ?>
            <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-left text-[11px] font-normal uppercase text-muted">Игрок</th>
                        <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">Account ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($players, 0, 20) as $player): ?>
                        <tr class="hover:bg-row-hover">
                            <td class="border-b border-line px-2.5 py-1.5">
                                <div class="flex items-center gap-2.5">
                                    <?php if (!empty($player['avatarfull'])): ?><img class="h-8 w-8 rounded object-cover" src="<?php echo e($player['avatarfull']); ?>" alt=""><?php endif; ?>
                                    <a class="font-bold text-link no-underline hover:underline" href="<?php echo e(player_url($player['account_id'] ?? 0)); ?>"><?php echo e($player['personaname'] ?? 'Игрок'); ?></a>
                                </div>
                            </td>
                            <td class="border-b border-line px-2.5 py-1.5 text-center"><?php echo e($player['account_id'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php elseif ($query !== '' && $match === null): ?>
            <div class="rounded-lg border border-line bg-black/15 p-3">Ничего не найдено.</div>
        <?php endif; ?>
    </section>
    <?php
}
