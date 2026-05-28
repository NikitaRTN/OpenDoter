<?php

declare(strict_types=1);
require_once __DIR__ . '/src/core.php';

try {
    $page = load_match_context($config);
    $current_tab = 'laning';
    extract($page, EXTR_SKIP);

    require __DIR__ . '/src/header.php';
    require __DIR__ . '/src/views/laning.php';
    require __DIR__ . '/src/footer.php';
} catch (Throwable $exception) {
    render_error_page(
        'Ошибка загрузки лейнинга',
        'Страница не использует заглушки. Исправьте источник данных и обновите страницу.',
        [
            $exception->getMessage(),
            'Проверьте, что локальный API запущен: ' . $config['api_base'],
            'Match ID: ' . $config['match_id'],
        ]
    );
}
