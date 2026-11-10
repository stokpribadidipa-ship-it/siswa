<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin']);
$pdo = db();
$kelas = normalize_text(input_string($_GET, 'kelas'));
$jurusan = normalize_text(input_string($_GET, 'jurusan'));
$classes = $pdo->query('SELECT DISTINCT kelas FROM siswa ORDER BY kelas')->fetchAll(PDO::FETCH_COLUMN);
$majors = $pdo->query('SELECT DISTINCT jurusan FROM siswa ORDER BY jurusan')->fetchAll(PDO::FETCH_COLUMN);
$invalidFilter = (isset($_GET['kelas']) && !is_string($_GET['kelas']))
    || (isset($_GET['jurusan']) && !is_string($_GET['jurusan']))
    || !in_array($kelas, array_merge([''], $classes), true)
    || !in_array($jurusan, array_merge([''], $majors), true);
if ($invalidFilter) {
    set_flash('warning', 'Filter laporan tidak valid. Pilih kelas atau jurusan yang tersedia.');
    redirect('laporan.php');
}
$conditions = [];
$parameters = [];
if ($kelas !== '') {
    $conditions[] = 'kelas = :kelas';
    $parameters['kelas'] = $kelas;
}
if ($jurusan !== '') {
    $conditions[] = 'jurusan = :jurusan';
    $parameters['jurusan'] = $jurusan;
}
$where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
$totalStatement = $pdo->prepare('SELECT COUNT(*) FROM siswa' . $where);
$totalStatement->execute($parameters);
$total = (int) $totalStatement->fetchColumn();
$classStatement = $pdo->prepare('SELECT kelas, COUNT(*) AS jumlah FROM siswa' . $where . ' GROUP BY kelas ORDER BY kelas');
$classStatement->execute($parameters);
$classSummary = $classStatement->fetchAll();
$majorStatement = $pdo->prepare('SELECT jurusan, COUNT(*) AS jumlah FROM siswa' . $where . ' GROUP BY jurusan ORDER BY jurusan');
$majorStatement->execute($parameters);
$majorSummary = $majorStatement->fetchAll();

render_header('Laporan', 'laporan.php', $user);
?>
<section class="panel no-print">
    <div class="panel-heading"><div><h2>Filter laporan</h2><p>Rekap akan mengikuti filter yang dipilih.</p></div></div>
    <form method="get" class="filter-form"><select name="kelas" class="form-select" aria-label="Filter kelas"><option value="">Semua kelas</option><?php foreach ($classes as $option): ?><option value="<?= e($option) ?>" <?= $kelas === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><select name="jurusan" class="form-select" aria-label="Filter jurusan"><option value="">Semua jurusan</option><?php foreach ($majors as $option): ?><option value="<?= e($option) ?>" <?= $jurusan === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Terapkan</button><a class="btn btn-light" href="laporan.php">Reset</a><button class="btn btn-outline-primary ms-auto" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button></form>
</section>
<section class="report-heading"><span class="eyebrow">LAPORAN DATA SISWA</span><h2>Rekapitulasi Siswa</h2><p>Dicetak pada <?= e(date('d/m/Y H:i')) ?></p></section>
<section class="report-total"><span>Total siswa sesuai filter</span><strong><?= e($total) ?></strong></section>
<div class="report-grid">
    <section class="panel"><div class="panel-heading"><div><h2>Jumlah berdasarkan kelas</h2></div></div><div class="table-responsive"><table class="table data-table mb-0"><thead><tr><th>Kelas</th><th>Jumlah siswa</th></tr></thead><tbody><?php if ($classSummary === []): ?><tr><td class="empty-state" colspan="2">Belum ada data.</td></tr><?php else: foreach ($classSummary as $row): ?><tr><td><?= e($row['kelas']) ?></td><td><?= e($row['jumlah']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
    <section class="panel"><div class="panel-heading"><div><h2>Jumlah berdasarkan jurusan</h2></div></div><div class="table-responsive"><table class="table data-table mb-0"><thead><tr><th>Jurusan</th><th>Jumlah siswa</th></tr></thead><tbody><?php if ($majorSummary === []): ?><tr><td class="empty-state" colspan="2">Belum ada data.</td></tr><?php else: foreach ($majorSummary as $row): ?><tr><td><?= e($row['jurusan']) ?></td><td><?= e($row['jumlah']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
</div>
<?php render_footer(); ?>
