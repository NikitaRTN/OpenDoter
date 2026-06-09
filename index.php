<?php

declare(strict_types=1);

// Single entry point (front controller).
//
// On classic PHP hosting Apache rewrites every non-file request to this script
// via .htaccess. When running the PHP built-in server for local development
// (`php -S localhost:8000 index.php`) this same script is used as the router,
// so existing static files (css, images, ...) must be served as-is.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if ($requestPath !== '/' && is_file(__DIR__ . $requestPath)) {
        return false;
    }
}

require_once __DIR__ . '/src/core.php';

try {
    $route = resolve_route($config);

    if ($route['type'] === 'login') {
        $account = resolve_account_input((string) ($route['account'] ?? ''));
        $redirect = app_url();
        if ($account !== null) {
            setcookie('opendoter_uid', $account, [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            $redirect = player_url($account);
        }
        header('Location: ' . $redirect);
        http_response_code(302);
        return;
    }

    if ($route['type'] === 'logout') {
        setcookie('opendoter_uid', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        header('Location: ' . app_url());
        http_response_code(302);
        return;
    }

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
        $home_account_id = current_account_id();
        $home_me = null;
        if ($home_account_id !== null) {
            try {
                $home_me = load_player_context($config, $home_account_id);
            } catch (Throwable $e) {
                $home_me = null;
            }
        }
        require __DIR__ . '/src/header.php';
        render_home_page($home_account_id, $home_me);
        require __DIR__ . '/src/footer.php';
        return;
    }

    if ($route['type'] === 'match') {
        $current_tab = (string) $route['tab'];
        $page = load_match_context($config, (string) $route['match_id']);
        extract($page, EXTR_SKIP);

        require __DIR__ . '/src/header.php';

        if (($parse_status ?? 'parsed') === 'metadata') {
            // Матч есть в OpenDota, но локально не распарсен — показываем
            // «главную» страницу с кнопкой «Запросить обработку».
            require __DIR__ . '/src/views/match_unparsed.php';
        } elseif ($current_tab === 'vision') {
            require __DIR__ . '/src/views/vision.php';
        } elseif ($current_tab === 'overview') {
            require __DIR__ . '/src/views/overview.php';
        } elseif ($current_tab === 'laning') {
            require __DIR__ . '/src/views/laning.php';
        } elseif (in_array($current_tab, match_tab_keys(), true)) {
            require __DIR__ . '/src/views/' . $current_tab . '.php';
        } else {
            render_error_box('Раздел не найден', 'Такой вкладки матча нет.', [
                'Матч доступен: ' . match_url((string) $match_id, 'overview'),
                'Вижен доступен: ' . match_url((string) $match_id, 'vision'),
                'Лейнинг доступен: ' . match_url((string) $match_id, 'laning'),
            ]);
        }
        require __DIR__ . '/src/footer.php';
        return;
    }

    if ($route['type'] === 'player') {
        $page_title = 'Профиль игрока';
        $matches_page = max(1, (int) ($_GET['page'] ?? 1));
        $include_turbo = in_array(strtolower((string) ($_GET['turbo'] ?? '')), ['1', 'true', 'yes', 'on'], true);
        $page = load_player_context($config, (string) $route['account_id'], $matches_page, $include_turbo);
        extract($page, EXTR_SKIP);

        require __DIR__ . '/src/header.php';
        render_player_profile($player_profile, $player_matches, $heroes, $player_stats ?? []);
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
