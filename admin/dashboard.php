<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin', 'petugas', 'siswa']);
$pdo = db();

$ownStudent = null;
if ($user['role'] === 'siswa' && $user['siswa_id'] !== null) {
    $ownStatement = $pdo->prepare('SELECT id, nis, nama, kelas, jurusan, alamat, jenis_kelamin, no_telepon, created_at, updated_at FROM siswa WHERE id = :id');
    $ownStatement->execute(['id' => $user['siswa_id']]);
    $ownStudent = $ownStatement->fetch() ?: null;
}

if ($user['role'] === 'siswa') {
    render_header('Dashboard', 'dashboard.php', $user);
    ?>
    <section class="welcome-banner">
        <div><span class="eyebrow">PORTAL SISWA</span><h2>Halo, <?= e($user['nama']) ?>.</h2><p>Di sini Anda dapat melihat profil data siswa milik Anda.</p></div>
        <span class="welcome-icon"><i class="bi bi-mortarboard"></i></span>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><h2>Profil saya</h2><p>Data pribadi yang tercatat di sekolah.</p></div></div>
        <?php if ($ownStudent === null): ?>
            <div class="alert alert-warning mb-0">Akun belum terhubung dengan data siswa. Hubungi admin sekolah untuk menautkan NIS Anda.</div>
        <?php else: ?>
            <dl class="detail-grid">
                <?php foreach ([
                    'NIS' => $ownStudent['nis'],
                    'Nama lengkap' => $ownStudent['nama'],
                    'Kelas' => $ownStudent['kelas'],
                    'Jurusan' => $ownStudent['jurusan'],
                    'Jenis kelamin' => $ownStudent['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
                    'Nomor telepon' => $ownStudent['no_telepon'],
                    'Alamat' => $ownStudent['alamat'],
                ] as $label => $value): ?>
                    <div><dt><?= e($label) ?></dt><dd><?= nl2br(e($value)) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
    exit;
}

$stats = [
    'siswa' => (int) $pdo->query('SELECT COUNT(*) FROM siswa')->fetchColumn(),
    'kelas' => (int) $pdo->query('SELECT COUNT(DISTINCT kelas) FROM siswa')->fetchColumn(),
    'jurusan' => (int) $pdo->query('SELECT COUNT(DISTINCT jurusan) FROM siswa')->fetchColumn(),
    'pengguna' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
];
$recentStudents = $pdo->query('SELECT id, nis, nama, kelas, jurusan, created_at FROM siswa ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();
$recentActivities = $pdo->query("SELECT activity, subject, created_at FROM (SELECT 'Siswa ditambahkan' AS activity, nama AS subject, created_at FROM siswa UNION ALL SELECT 'Pengguna terdaftar' AS activity, nama AS subject, created_at FROM users) AS activity_log ORDER BY created_at DESC LIMIT 5")->fetchAll();

render_header('Dashboard', 'dashboard.php', $user);
?>
<section class="welcome-banner">
    <div><span class="eyebrow">RINGKASAN SEKOLAH</span><h2>Halo, <?= e($user['nama']) ?>.</h2><p>Berikut ringkasan data yang tersimpan di aplikasi.</p></div>
    <span class="welcome-icon"><i class="bi bi-mortarboard"></i></span>
</section>
<section class="stats-grid" aria-label="Statistik">
    <?php foreach ([
        ['Total siswa', $stats['siswa'], 'bi-people', 'blue'],
        ['Total kelas', $stats['kelas'], 'bi-door-open', 'purple'],
        ['Total jurusan', $stats['jurusan'], 'bi-diagram-3', 'green'],
        ...($user['role'] === 'admin' ? [['Total pengguna', $stats['pengguna'], 'bi-person-check', 'orange']] : []),
    ] as [$label, $value, $icon, $color]): ?>
        <article class="stat-card">
            <span class="stat-icon <?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></span>
            <span class="stat-label"><?= e($label) ?></span>
            <strong class="stat-value"><?= e($value) ?></strong>
            <span class="stat-note">Data terbaru dari database</span>
        </article>
    <?php endforeach; ?>
</section>
<div class="dashboard-split">
    <?php if (in_array($user['role'], ['admin', 'petugas'], true)): ?><section class="panel">
        <div class="panel-heading"><div><h2>Siswa terbaru</h2><p>Lima data siswa yang terakhir ditambahkan.</p></div><a class="btn btn-outline-primary btn-sm" href="siswa.php">Lihat semua <i class="bi bi-arrow-right"></i></a></div>
        <div class="table-responsive">
            <table class="table align-middle data-table mb-0">
                <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jurusan</th><th>Ditambahkan</th></tr></thead>
                <tbody>
                <?php if ($recentStudents === []): ?>
                    <tr><td class="empty-state" colspan="5">Belum ada data siswa.<?php if ($user['role'] === 'admin'): ?> <a href="siswa-form.php">Tambahkan siswa pertama</a>.<?php endif; ?></td></tr>
                <?php else: foreach ($recentStudents as $student): ?>
                    <tr>
                        <td><?= e($student['nis']) ?></td>
                        <td><a class="table-primary-link" href="siswa-detail.php?id=<?= e($student['id']) ?>"><?= e($student['nama']) ?></a></td>
                        <td><?= e($student['kelas']) ?></td>
                        <td><?= e($student['jurusan']) ?></td>
                        <td><?= e(date('d/m/Y', strtotime($student['created_at']))) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
    <?php if ($user['role'] === 'admin'): ?><section class="panel">
        <div class="panel-heading"><div><h2>Aktivitas terbaru</h2><p>Aktivitas akun dan data di aplikasi.</p></div></div>
        <?php if ($recentActivities === []): ?><p class="empty-state">Belum ada aktivitas.</p>
        <?php else: ?><ul class="activity-list">
            <?php foreach ($recentActivities as $activity): ?>
                <li><span class="activity-icon"><i class="bi <?= $activity['activity'] === 'Siswa ditambahkan' ? 'bi-person-plus' : 'bi-person-check' ?>"></i></span><span class="activity-copy"><strong><?= e($activity['activity']) ?></strong><small><?= e($activity['subject']) ?></small><time><?= e(date('d/m/Y H:i', strtotime($activity['created_at']))) ?></time></span></li>
            <?php endforeach; ?>
        </ul><?php endif; ?>
    </section><?php endif; ?>
</div>
<div class="quick-actions">
    <?php if ($user['role'] === 'admin'): ?><a class="quick-action" href="siswa.php"><i class="bi bi-search"></i><span><strong>Jelajahi data siswa</strong><small>Cari dan lihat informasi siswa</small></span><i class="bi bi-arrow-up-right"></i></a><?php endif; ?>
    <?php if ($user['role'] === 'petugas'): ?><a class="quick-action" href="siswa.php"><i class="bi bi-people"></i><span><strong>Data siswa</strong><small>Lihat dan cari informasi siswa</small></span><i class="bi bi-arrow-up-right"></i></a><?php endif; ?>
    <?php if ($user['role'] === 'admin'): ?>
        <a class="quick-action" href="siswa-form.php"><i class="bi bi-person-plus"></i><span><strong>Tambah siswa baru</strong><small>Masukkan data siswa ke sistem</small></span><i class="bi bi-arrow-up-right"></i></a>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
