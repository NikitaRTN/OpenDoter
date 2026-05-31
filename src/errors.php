<?php
declare(strict_types=1);

function render_error_box(string $title, string $message, array $details = []): void
{
    ?>
    <div class="rounded border border-l-4 border-dire bg-[#3a1014] px-4 py-3.5 text-[#ffd9d9]" role="alert">
        <strong class="mb-1.5 block text-base text-white"><?php echo e($title); ?></strong>
        <p class="m-0 mb-2.5"><?php echo e($message); ?></p>
        <?php if ($details !== []): ?>
            <ul class="m-0 list-disc pl-5">
                <?php foreach ($details as $detail): ?>
                    <li><?php echo e((string) $detail); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
}

function render_error_page(string $title, string $message, array $details = []): void
{
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo e($title); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                corePlugins: { preflight: false },
                theme: { extend: { colors: {
                    base: '#0f1417', surface: '#1c242d', main: '#d6d9dc', dire: '#e74c3c',
                }, boxShadow: { card: '0 4px 15px rgba(0, 0, 0, 0.5)' } } },
            };
        </script>
        <link rel="stylesheet" href="<?php echo e(asset_url('css/root.css')); ?>">
        <link rel="stylesheet" href="<?php echo e(asset_url('css/base.css')); ?>">
    </head>
    <body class="bg-base text-main">
        <main class="mx-auto max-w-[1200px] rounded-lg bg-surface p-5 shadow-card">
            <?php render_error_box($title, $message, $details); ?>
        </main>
    </body>
    </html>
    <?php
}
