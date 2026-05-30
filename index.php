<?php

declare(strict_types=1);

require_once __DIR__ . '/src/core.php';

try {
    $route = resolve_route($config);

    if ($route['type'] === 'api_status') {
        $api_base = rtrim((string) $config['api_base'], '/');
        proxy_json_request($api_base . '/api/status/' . rawurlencode((string) $route['match_id']), 'GET', null, (float) $config['request_timeout']);
        return;
    }

    if ($route['type'] === 'api_parse') {
        $api_base = rtrim((string) $config['api_base'], '/');
        $body = file_get_contents('php://input') ?: '{}';
        proxy_json_request($api_base . '/api/parse', 'POST', $body, (float) $config['request_timeout']);
        return;
    }

    if ($route['type'] === 'home') {
        $page_title = 'OpenDoter';
        require __DIR__ . '/src/header.php';
        ?>
        <section class="profile-panel">
            <div class="team-header">
                <div>
                    <span class="team-title">OpenDoter</span>
                    <span class="team-subtitle"> - поиск матчей и игроков</span>
                </div>
            </div>
            <div class="empty-state">
                <span>Введите ник игрока или Match ID в поиск сверху.</span>
                <a class="profile-link" href="<?php echo e(app_url('search?q=dendi')); ?>">Пример поиска</a>
            </div>
        </section>
        <?php
        require __DIR__ . '/src/footer.php';
        return;
    }

    if ($route['type'] === 'match') {
        $current_tab = (string) $route['tab'];
        $page = load_match_context($config, (string) $route['match_id']);
        extract($page, EXTR_SKIP);

        $known_tabs = [
            'overview', 'benchmarks', 'stats', 'laning', 'damage', 'gold', 'items',
            'graphs', 'abilities', 'objectives', 'vision', 'actions', 'teamfights',
            'fantasy', 'chat', 'story', 'log', 'cosmetics',
        ];

        require __DIR__ . '/src/header.php';

        $view_file = __DIR__ . '/src/views/' . $current_tab . '.php';
        if (in_array($current_tab, $known_tabs, true) && is_file($view_file)) {
            require $view_file;
        } else {
            render_error_box('Раздел не найден', 'Такой вкладки у матча нет.', [
                'Обзор доступен: ' . match_url((string) $match_id, 'overview'),
            ]);
        }
        require __DIR__ . '/src/footer.php';
        return;
    }

    if ($route['type'] === 'player') {
        $page_title = 'Профиль игрока';
        $page = load_player_context($config, (string) $route['account_id']);
        extract($page, EXTR_SKIP);

        require __DIR__ . '/src/header.php';
        render_player_profile($player_profile, $player_matches, $heroes);
        require __DIR__ . '/src/footer.php';
        return;
    }

    if ($route['type'] === 'search') {
        $page_title = 'Поиск';
        $search_query = (string) $route['query'];
        $page = load_search_context($config, $search_query);
        extract($page, EXTR_SKIP);

        require __DIR__ . '/src/header.php';
        render_search_page($query, $search_players, $search_match);
        require __DIR__ . '/src/footer.php';
        return;
    }

    http_response_code(404);
    $page_title = '404';
    require __DIR__ . '/src/header.php';
    render_error_box('Страница не найдена', 'Такого маршрута нет.', [
        'Откройте матч через /matches/{match_id}/overview',
        'Или воспользуйтесь поиском сверху.',
    ]);
    require __DIR__ . '/src/footer.php';
} catch (Throwable $exception) {
    render_error_page(
        'Ошибка загрузки данных',
        'Страница не использует заглушки. Исправьте источник данных и обновите страницу.',
        [
            $exception->getMessage(),
            'Проверьте, что локальный API запущен: ' . $config['api_base'],
            'Match ID: ' . ($route['match_id'] ?? $config['match_id']),
        ]
    );
}
