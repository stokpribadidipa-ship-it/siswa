<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin', 'petugas', 'siswa']);
if ($user['role'] === 'siswa') {
    redirect('dashboard.php');
}
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_auth(['admin']);
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        set_flash('danger', 'ID siswa tidak valid.');
    } else {
        try {
            $statement = $pdo->prepare('DELETE FROM siswa WHERE id = :id');
            $statement->execute(['id' => $id]);
            set_flash($statement->rowCount() === 1 ? 'success' : 'warning', $statement->rowCount() === 1 ? 'Data siswa berhasil dihapus.' : 'Data siswa tidak ditemukan.');
        } catch (Throwable $error) {
            report_database_error($error);
        }
    }
    redirect('siswa.php');
}

$classes = $pdo->query('SELECT DISTINCT kelas FROM siswa ORDER BY kelas')->fetchAll(PDO::FETCH_COLUMN);
$majors = $pdo->query('SELECT DISTINCT jurusan FROM siswa ORDER BY jurusan')->fetchAll(PDO::FETCH_COLUMN);
$search = normalize_text(input_string($_GET, 'q'));
$kelas = normalize_text(input_string($_GET, 'kelas'));
$jurusan = normalize_text(input_string($_GET, 'jurusan'));
$sort = input_string($_GET, 'sort') ?: 'nama';
$directionInput = strtolower(input_string($_GET, 'direction') ?: 'asc');
$pageInput = input_string($_GET, 'page') ?: '1';

$invalidQuery = (isset($_GET['q']) && !is_string($_GET['q']))
    || (isset($_GET['kelas']) && !is_string($_GET['kelas']))
    || (isset($_GET['jurusan']) && !is_string($_GET['jurusan']))
    || (isset($_GET['sort']) && !is_string($_GET['sort']))
    || (isset($_GET['direction']) && !is_string($_GET['direction']))
    || (isset($_GET['page']) && !is_string($_GET['page']))
    || text_length($search) > 100
    || !in_array($kelas, array_merge([''], $classes), true)
    || !in_array($jurusan, array_merge([''], $majors), true)
    || !in_array($sort, ['nama', 'nis'], true)
    || !in_array($directionInput, ['asc', 'desc'], true)
    || preg_match('/^[1-9][0-9]*$/D', $pageInput) !== 1;

if ($invalidQuery) {
    set_flash('warning', 'Pencarian atau filter tidak valid. Silakan pilih nilai yang tersedia.');
    redirect('siswa.php');
}

$sortColumn = ['nama' => 'nama', 'nis' => 'nis'][$sort];
$direction = strtoupper($directionInput);
$page = (int) $pageInput;
$perPage = 10;

$where = [];
$parameters = [];
if ($search !== '') {
    $where[] = '(nis LIKE :search_nis OR nama LIKE :search_nama OR kelas LIKE :search_kelas OR jurusan LIKE :search_jurusan)';
    $pattern = '%' . $search . '%';
    $parameters += ['search_nis' => $pattern, 'search_nama' => $pattern, 'search_kelas' => $pattern, 'search_jurusan' => $pattern];
}
if ($kelas !== '') {
    $where[] = 'kelas = :kelas';
    $parameters['kelas'] = $kelas;
}
if ($jurusan !== '') {
    $where[] = 'jurusan = :jurusan';
    $parameters['jurusan'] = $jurusan;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$countStatement = $pdo->prepare('SELECT COUNT(*) FROM siswa' . $whereSql);
$countStatement->execute($parameters);
$total = (int) $countStatement->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$listStatement = $pdo->prepare("SELECT id, nis, nama, kelas, jurusan, jenis_kelamin, no_telepon FROM siswa{$whereSql} ORDER BY {$sortColumn} {$direction}, id ASC LIMIT :limit OFFSET :offset");
foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$students = $listStatement->fetchAll();

render_header('Data Siswa', 'siswa.php', $user);
?>
<section class="panel">
    <div class="panel-heading"><div><h2>Daftar siswa</h2><p><?= e($total) ?> data sesuai pencarian dan filter.</p></div>
        <?php if ($user['role'] === 'admin'): ?><a class="btn btn-primary" href="siswa-form.php"><i class="bi bi-plus-lg"></i> Tambah siswa</a><?php endif; ?>
    </div>
    <form method="get" class="filter-form">
        <label class="search-control"><i class="bi bi-search"></i><input name="q" type="search" maxlength="100" value="<?= e($search) ?>" placeholder="Cari NIS, nama, kelas, atau jurusan"></label>
        <select name="kelas" class="form-select" aria-label="Filter kelas"><option value="">Semua kelas</option><?php foreach ($classes as $option): ?><option value="<?= e($option) ?>" <?= $kelas === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
        <select name="jurusan" class="form-select" aria-label="Filter jurusan"><option value="">Semua jurusan</option><?php foreach ($majors as $option): ?><option value="<?= e($option) ?>" <?= $jurusan === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
        <select name="sort" class="form-select" aria-label="Urutkan berdasarkan"><option value="nama" <?= $sortColumn === 'nama' ? 'selected' : '' ?>>Urut: nama</option><option value="nis" <?= $sortColumn === 'nis' ? 'selected' : '' ?>>Urut: NIS</option></select>
        <select name="direction" class="form-select" aria-label="Arah pengurutan"><option value="asc" <?= $direction === 'ASC' ? 'selected' : '' ?>>A–Z / naik</option><option value="desc" <?= $direction === 'DESC' ? 'selected' : '' ?>>Z–A / turun</option></select>
        <button class="btn btn-outline-primary" type="submit">Terapkan</button>
    </form>
    <div class="table-responsive">
        <table class="table align-middle data-table mb-0">
            <thead><tr><th>No.</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jurusan</th><th>Jenis kelamin</th><th>Telepon</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if ($students === []): ?><tr><td class="empty-state" colspan="8">Tidak ada data siswa yang cocok dengan filter.</td></tr>
            <?php else: foreach ($students as $index => $student): ?>
                <tr>
                    <td><?= e($offset + $index + 1) ?></td><td><?= e($student['nis']) ?></td>
                    <td><a class="table-primary-link" href="siswa-detail.php?id=<?= e($student['id']) ?>"><?= e($student['nama']) ?></a></td>
                    <td><?= e($student['kelas']) ?></td><td><?= e($student['jurusan']) ?></td>
                    <td><?= $student['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td><td><?= e($student['no_telepon']) ?></td>
                    <td><div class="table-actions">
                        <a class="btn btn-sm btn-light" href="siswa-detail.php?id=<?= e($student['id']) ?>" aria-label="Detail siswa"><i class="bi bi-eye"></i></a>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a class="btn btn-sm btn-light" href="siswa-form.php?id=<?= e($student['id']) ?>" aria-label="Edit siswa"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="delete-form" data-confirm="Hapus data siswa ini? Tindakan ini tidak dapat dibatalkan.">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($student['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Hapus siswa"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pagination-wrap" aria-label="Halaman daftar siswa"><span>Halaman <?= e($page) ?> dari <?= e($pages) ?></span><div class="btn-group">
            <?php foreach (array_filter([$page > 1 ? $page - 1 : null, $page, $page < $pages ? $page + 1 : null]) as $pageNumber): ?>
                <?php $query = $_GET; $query['page'] = $pageNumber; ?>
                <a class="btn btn-sm <?= $pageNumber === $page ? 'btn-primary' : 'btn-outline-primary' ?>" href="?<?= e(http_build_query($query)) ?>"><?= e($pageNumber) ?></a>
            <?php endforeach; ?>
        </div></nav>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
