<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function render_header(string $title, string $active, array $user): void
{
    $adminLinks = [
        ['Dashboard', 'dashboard.php', 'bi-grid-1x2'],
        ['Data Siswa', 'siswa.php', 'bi-people'],
        ['Tambah Siswa', 'siswa-form.php', 'bi-person-plus'],
        ['Data Pengguna', 'users.php', 'bi-person-gear'],
        ['Laporan', 'laporan.php', 'bi-file-earmark-bar-graph'],
    ];
    $staffLinks = [
        ['Dashboard', 'dashboard.php', 'bi-grid-1x2'],
        ['Data Siswa', 'siswa.php', 'bi-people'],
    ];
    $studentLinks = [
        ['Dashboard', 'dashboard.php', 'bi-grid-1x2'],
        ['Profil Saya', 'siswa-detail.php', 'bi-person-vcard'],
    ];
    $links = match ($user['role']) {
        'admin' => $adminLinks,
        'petugas' => $staffLinks,
        default => $studentLinks,
    };
    $flash = get_flash();
    $inAdminDirectory = basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin';
    $prefix = $inAdminDirectory ? '' : 'admin/';
    $assetPrefix = $inAdminDirectory ? '../' : '';
    $accountHref = $inAdminDirectory ? 'pengaturan.php' : 'account.php';
    $logoutHref = $inAdminDirectory ? '../logout.php' : 'logout.php';
    $initial = function_exists('mb_substr') ? mb_substr($user['nama'], 0, 1, 'UTF-8') : substr($user['nama'], 0, 1);
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | DataSiswa</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="<?= $assetPrefix ?>assets/css/style.css?v=<?= e((string) filemtime(__DIR__ . '/../assets/css/style.css')) ?>" rel="stylesheet">
    </head>
    <body class="app-body">
        <div class="app-layout">
            <aside class="sidebar" id="appSidebar">
                <a class="brand" href="<?= $prefix ?>dashboard.php">
                    <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
                    <span><strong>DataSiswa</strong><small>Manajemen sekolah</small></span>
                </a>
                <div class="nav-caption">MENU UTAMA</div>
                <nav class="side-nav" aria-label="Navigasi utama">
                    <?php foreach ($links as [$label, $href, $icon]): ?>
                        <a class="<?= $active === $href ? 'active' : '' ?>" href="<?= $prefix . e($href) ?>">
                            <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <a class="<?= in_array($active, ['account.php', 'pengaturan.php'], true) ? 'active' : '' ?>" href="<?= e($accountHref) ?>">
                        <i class="bi bi-gear"></i><span>Pengaturan Akun</span>
                    </a>
                </nav>
                <div class="sidebar-bottom">
                    <div class="user-summary">
                        <span class="avatar"><?= e($initial) ?></span>
                        <span class="user-label"><strong><?= e($user['nama']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></span>
                    </div>
                    <form action="<?= e($logoutHref) ?>" method="post">
                        <?= csrf_field() ?>
                        <button class="logout-button" type="submit"><i class="bi bi-box-arrow-right"></i>Keluar</button>
                    </form>
                </div>
            </aside>
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            <div class="main-column">
                <header class="topbar">
                    <button class="menu-toggle" id="menuToggle" type="button" aria-label="Buka menu" aria-controls="appSidebar" aria-expanded="false">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="topbar-title"><span>APLIKASI MANAJEMEN DATA SISWA</span><h1><?= e($title) ?></h1></div>
                    <a class="profile-chip" href="<?= e($accountHref) ?>">
                        <span class="avatar"><?= e($initial) ?></span>
                        <span><strong><?= e($user['nama']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></span>
                    </a>
                </header>
                <main class="main-content">
                    <?php if ($flash !== null): ?>
                        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                            <?= e($flash['message']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                        </div>
                    <?php endif; ?>
    <?php
}

function render_footer(): void
{
    $inAdminDirectory = basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin';
    $assetPrefix = $inAdminDirectory ? '../' : '';
    ?>
                </main>
                <footer class="app-footer">DataSiswa &middot; Aplikasi Manajemen Data Siswa</footer>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="<?= $assetPrefix ?>assets/js/site.js?v=<?= e((string) filemtime(__DIR__ . '/../assets/js/site.js')) ?>"></script>
    </body>
    </html>
    <?php
}
