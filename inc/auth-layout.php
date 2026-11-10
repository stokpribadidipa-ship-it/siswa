<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function render_auth_start(string $title): void
{
    $flash = get_flash();
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | DataSiswa</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="assets/css/style.css?v=<?= e((string) filemtime(__DIR__ . '/../assets/css/style.css')) ?>" rel="stylesheet">
    </head>
    <body class="auth-body">
        <main class="auth-page">
            <section class="auth-intro">
                <a class="brand auth-brand" href="index.php"><span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span><strong>DataSiswa</strong></a>
                <div>
                    <span class="eyebrow">PORTAL SEKOLAH</span>
                    <h1>Data yang tertata.<br>Belajar lebih mudah.</h1>
                    <p>Aplikasi sederhana untuk mengelola informasi siswa dengan aman dan efisien.</p>
                </div>
                <span class="intro-footer">Manajemen data siswa &middot; PHP dan MySQL</span>
            </section>
            <section class="auth-panel">
                <div class="auth-card">
                    <div class="auth-heading"><span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span><span class="eyebrow">DATA SISWA</span></div>
                    <?php if ($flash !== null): ?>
                        <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
                    <?php endif; ?>
    <?php
}

function render_auth_end(): void
{
    ?>
                </div>
            </section>
        </main>
        <script src="assets/js/site.js?v=<?= e((string) filemtime(__DIR__ . '/../assets/js/site.js')) ?>"></script>
    </body>
    </html>
    <?php
}
