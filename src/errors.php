<?php
declare(strict_types=1);

function render_error_box(string $title, string $message, array $details = []): void
{
    ?>
    <div class="error-box" role="alert">
        <strong><?php echo e($title); ?></strong>
        <p><?php echo e($message); ?></p>
        <?php if ($details !== []): ?>
            <ul>
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
        <link rel="stylesheet" href="<?php echo e(asset_url('css/root.css')); ?>">
        <link rel="stylesheet" href="<?php echo e(asset_url('css/base.css')); ?>">
    </head>
    <body>
        <main class="container">
            <?php render_error_box($title, $message, $details); ?>
        </main>
    </body>
    </html>
    <?php
}
