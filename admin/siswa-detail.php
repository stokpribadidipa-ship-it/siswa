<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin', 'petugas', 'siswa']);
$requestedId = $_GET['id'] ?? null;
if ($user['role'] === 'siswa') {
    $id = (int) ($user['siswa_id'] ?? 0);
    if ($id < 1 || ($requestedId !== null && (!is_string($requestedId) || $requestedId !== (string) $id))) {
        http_response_code(404);
        exit('Profil siswa tidak ditemukan.');
    }
} else {
    $id = filter_var($requestedId, FILTER_VALIDATE_INT);
}
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID siswa tidak valid.');
}
$statement = db()->prepare('SELECT s.*, k.nama AS nama_kelas, j.nama AS nama_jurusan FROM siswa s JOIN kelas k ON k.id=s.kelas_id JOIN jurusan j ON j.id=s.jurusan_id WHERE s.id=:id');
$statement->execute(['id' => $id]);
$student = $statement->fetch();
if (!$student) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

render_header('Detail Siswa', 'siswa.php', $user);
?>
<section class="panel detail-panel">
    <div class="panel-heading"><div><h2><?= e($student['nama']) ?></h2><p>Informasi lengkap siswa</p></div><div class="d-flex gap-2"><a class="btn btn-outline-primary" href="siswa.php"><i class="bi bi-arrow-left"></i> Kembali</a><?php if ($user['role'] === 'admin'): ?><a class="btn btn-primary" href="siswa-form.php?id=<?= e($student['id']) ?>"><i class="bi bi-pencil"></i> Edit</a><?php endif; ?></div></div>
    <?php if (!empty($student['foto'])): ?><img src="../assets/foto/<?= e(basename($student['foto'])) ?>" alt="Foto <?= e($student['nama']) ?>" class="student-photo-detail"><?php else: ?><div class="student-photo-placeholder detail-placeholder"><i class="bi bi-person"></i></div><?php endif; ?>
    <dl class="detail-grid">
        <?php foreach ([
            'NIS' => $student['nis'],
            'Nama lengkap' => $student['nama'],
            'Kelas' => $student['nama_kelas'],
            'Jurusan' => $student['nama_jurusan'],
            'Jenis kelamin' => $student['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
            'Nomor telepon' => $student['no_telepon'],
            'Alamat' => $student['alamat'],
            'Tanggal ditambahkan' => date('d/m/Y H:i', strtotime($student['created_at'])),
            'Terakhir diperbarui' => date('d/m/Y H:i', strtotime($student['updated_at'])),
        ] as $label => $value): ?>
            <div><dt><?= e($label) ?></dt><dd><?= nl2br(e($value)) ?></dd></div>
        <?php endforeach; ?>
    </dl>
</section>
<?php render_footer(); ?>
