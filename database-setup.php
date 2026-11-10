<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

$missingTables = missing_schema_requirements();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Persiapan Database | DataSiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body">
    <main class="auth-panel min-vh-100">
        <section class="auth-card">
            <span class="eyebrow">PERSIAPAN APLIKASI</span>
            <h1 class="auth-title">Database belum siap</h1>
            <?php if ($missingTables !== []): ?>
                <p class="auth-description">Tabel berikut belum tersedia di database <strong>db_sekolah</strong>: <?= e(implode(', ', $missingTables)) ?>.</p>
                <ol class="small text-secondary">
                    <li>Buka <code>http://localhost/phpmyadmin</code>.</li>
                    <li>Pilih menu <strong>Import</strong> dan impor <code>database.sql</code> untuk instalasi baru, atau <code>migration-roles.sql</code> untuk database lama.</li>
                    <li>Muat ulang halaman ini setelah proses import berhasil.</li>
                </ol>
                <div class="alert alert-warning mb-0" role="alert">Database lama perlu migrasi satu kali. Akun role lama <code>user</code> akan menjadi <code>siswa</code>; admin perlu menautkan akun tersebut ke NIS yang benar.</div>
            <?php else: ?>
                <p class="auth-description">Tabel aplikasi sudah tersedia. Silakan kembali ke halaman <a href="index.php">login</a>.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
