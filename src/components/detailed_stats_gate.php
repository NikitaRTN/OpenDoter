<?php

declare(strict_types=1);

function render_detailed_stats_gate(string $match_id, string $module_name = 'подробной статистики', bool $is_available = false): void
{
    if ($is_available) {
        ?>
        <script>
            window.detailedStatsGate = {
                matchId: <?php echo json_encode($match_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                isAvailable: true,
                parseUrl: <?php echo json_encode(app_url('api/parse'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                statusUrl: <?php echo json_encode(app_url('api/status/' . $match_id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            };
        </script>
        <?php
        return;
    }

    ?>
    <section class="stats-gate" data-stats-gate hidden>
        <div class="stats-gate-card" role="status" aria-live="polite">
            <div class="stats-gate-title">запись не обработана</div>
            <div class="stats-gate-text">Для просмотра <?php echo e($module_name); ?> нужно запросить обработку матча.</div>
            <button class="stats-gate-button" type="button" data-stats-gate-request>Запросить обработку</button>
            <div class="stats-gate-status" data-stats-gate-status></div>
        </div>
    </section>
    <div class="stats-toast" data-stats-toast hidden></div>
    <script>
        window.detailedStatsGate = {
            matchId: <?php echo json_encode($match_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            isAvailable: <?php echo json_encode($is_available); ?>,
            parseUrl: <?php echo json_encode(app_url('api/parse'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            statusUrl: <?php echo json_encode(app_url('api/status/' . $match_id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        };
    </script>
    <?php
}
