<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin', 'petugas', 'siswa']);
if ($user['role'] === 'siswa') {
    redirect('dashboard.php');
}
$pdo = db();
$sendJson = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax'] ?? '') === 'student') {
    require_auth(['admin']);
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        $sendJson(['success' => false, 'message' => 'ID siswa tidak valid.', 'data' => null, 'errors' => []], 400);
    }

    $statement = $pdo->prepare('SELECT s.id, s.nis, s.nama, s.kelas_id, s.jurusan_id, k.nama AS kelas, j.nama AS jurusan, s.alamat, s.jenis_kelamin, s.no_telepon, s.foto FROM siswa s JOIN kelas k ON k.id=s.kelas_id JOIN jurusan j ON j.id=s.jurusan_id WHERE s.id=:id');
    $statement->execute(['id' => $id]);
    $student = $statement->fetch();
    if (!$student) {
        $sendJson(['success' => false, 'message' => 'Data siswa tidak ditemukan.', 'data' => null, 'errors' => []], 404);
    }

    $sendJson(['success' => true, 'message' => 'Data siswa berhasil dimuat.', 'data' => $student, 'errors' => []]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
    require_auth(['admin']);
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals(csrf_token(), $csrfToken)) {
        $sendJson(['success' => false, 'message' => 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.', 'data' => null, 'errors' => []], 400);
    }

    $action = input_string($_POST, 'action');

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            $sendJson(['success' => false, 'message' => 'ID siswa tidak valid.', 'data' => null, 'errors' => []], 400);
        }

        try {
            $photoStatement = $pdo->prepare('SELECT foto FROM siswa WHERE id=:id');
            $photoStatement->execute(['id' => $id]);
            $photo = $photoStatement->fetchColumn();
            $photoStatement = $pdo->prepare('SELECT foto FROM siswa WHERE id=:id');
            $photoStatement->execute(['id' => $id]);
            $photo = $photoStatement->fetchColumn();
            $statement = $pdo->prepare('DELETE FROM siswa WHERE id = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() === 1) {
                delete_student_photo(is_string($photo) ? $photo : null);
            }
            if ($statement->rowCount() !== 1) {
                $sendJson(['success' => false, 'message' => 'Data siswa tidak ditemukan.', 'data' => null, 'errors' => []], 404);
            }
            delete_student_photo(is_string($photo) ? $photo : null);
        } catch (Throwable $error) {
            report_database_error($error);
            $sendJson(['success' => false, 'message' => 'Hapus data gagal. Coba lagi nanti.', 'data' => null, 'errors' => []], 500);
        }

        $sendJson(['success' => true, 'message' => 'Data siswa berhasil dihapus.', 'data' => ['id' => $id], 'errors' => []]);
    }

    if (!in_array($action, ['create', 'update'], true)) {
        $sendJson(['success' => false, 'message' => 'Aksi tidak dikenali.', 'data' => null, 'errors' => []], 400);
    }

    $studentData = ['nis' => '', 'nama' => '', 'kelas_id' => '', 'jurusan_id' => '', 'alamat' => '', 'jenis_kelamin' => 'L', 'no_telepon' => '', 'foto' => null];
    foreach (array_keys($studentData) as $field) {
        $studentData[$field] = input_string($_POST, $field);
    }
    $studentData['nis'] = trim($studentData['nis']);
    $studentData['nama'] = normalize_text($studentData['nama']);
    $studentData['kelas_id'] = filter_var($_POST['kelas_id'] ?? null, FILTER_VALIDATE_INT);
    $studentData['jurusan_id'] = filter_var($_POST['jurusan_id'] ?? null, FILTER_VALIDATE_INT);
    $studentData['no_telepon'] = normalize_phone_number($studentData['no_telepon']);
    $studentData['alamat'] = trim($studentData['alamat']);

    $errors = [];
    if (!valid_student_identifier($studentData['nis'])) {
        $errors[] = 'NIS wajib diisi tepat 10 angka.';
    }
    if (!valid_person_name($studentData['nama'])) {
        $errors[] = 'Nama harus terdiri dari 2–100 karakter dan hanya berisi huruf, spasi, titik, apostrof, atau tanda hubung.';
    }
    $classExists = $studentData['kelas_id'] ? $pdo->prepare('SELECT 1 FROM kelas WHERE id=:id') : null;
    if ($classExists) { $classExists->execute(['id' => $studentData['kelas_id']]); }
    $majorExists = $studentData['jurusan_id'] ? $pdo->prepare('SELECT 1 FROM jurusan WHERE id=:id') : null;
    if ($majorExists) { $majorExists->execute(['id' => $studentData['jurusan_id']]); }
    if (!$classExists || !$classExists->fetch()) $errors[] = 'Pilih kelas yang valid.';
    if (!$majorExists || !$majorExists->fetch()) $errors[] = 'Pilih jurusan yang valid.';
    if (!valid_phone_number($studentData['no_telepon'])) {
        $errors[] = 'Nomor HP harus berisi 10–13 angka.';
    }
    if (!valid_postal_address($studentData['alamat'])) {
        $errors[] = 'Alamat wajib diisi, panjangnya 5–2000 karakter, dan tidak boleh berisi tag HTML atau karakter kontrol.';
    }
    if (!in_array($studentData['jenis_kelamin'], ['L', 'P'], true)) {
        $errors[] = 'Pilih jenis kelamin yang valid.';
    }

    $targetId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $editing = $action === 'update';
    if ($editing && (!$targetId || $targetId < 1)) {
        $errors[] = 'ID siswa tidak valid untuk diperbarui.';
    }

    if ($errors === []) {
        if ($editing) {
            $existing = $pdo->prepare('SELECT id FROM siswa WHERE id = :id');
            $existing->execute(['id' => $targetId]);
            if (!$existing->fetch()) {
                $sendJson(['success' => false, 'message' => 'Data siswa tidak ditemukan.', 'data' => null, 'errors' => []], 404);
            }
        }
        $duplicate = $pdo->prepare('SELECT id FROM siswa WHERE nis = :nis AND id <> :id LIMIT 1');
        $duplicate->execute(['nis' => $studentData['nis'], 'id' => $editing ? $targetId : 0]);
        if ($duplicate->fetch()) {
            $errors[] = 'NIS tersebut sudah digunakan siswa lain.';
        }
    }

    if ($errors !== []) {
        $sendJson(['success' => false, 'message' => 'Data siswa belum valid.', 'data' => null, 'errors' => $errors], 422);
    }

    try {
        if ($editing) {
            $statement = $pdo->prepare('UPDATE siswa SET nis = :nis, nama = :nama, kelas_id = :kelas_id, jurusan_id = :jurusan_id, alamat = :alamat, jenis_kelamin = :jenis_kelamin, no_telepon = :no_telepon WHERE id = :id');
            $studentData['id'] = $targetId;
            $statement->execute($studentData);
            $studentId = $targetId;
        } else {
            $statement = $pdo->prepare('INSERT INTO siswa (nis, nama, kelas_id, jurusan_id, alamat, jenis_kelamin, no_telepon) VALUES (:nis, :nama, :kelas_id, :jurusan_id, :alamat, :jenis_kelamin, :no_telepon)');
            $statement->execute($studentData);
            $studentId = (int) $pdo->lastInsertId();
        }
    } catch (Throwable $error) {
        report_database_error($error);
        $sendJson(['success' => false, 'message' => 'Data gagal disimpan. Periksa kembali isian Anda.', 'data' => null, 'errors' => []], 500);
    }

    $sendJson([
        'success' => true,
        'message' => $editing ? 'Data siswa berhasil diperbarui.' : 'Data siswa berhasil ditambahkan.',
        'data' => ['id' => $studentId],
        'errors' => [],
    ]);
}

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

$classes = $pdo->query('SELECT nama FROM kelas ORDER BY nama')->fetchAll(PDO::FETCH_COLUMN);
$majors = $pdo->query('SELECT nama FROM jurusan ORDER BY nama')->fetchAll(PDO::FETCH_COLUMN);
$classOptions = $pdo->query('SELECT id, nama FROM kelas ORDER BY nama')->fetchAll();
$majorOptions = $pdo->query('SELECT id, nama FROM jurusan ORDER BY nama')->fetchAll();
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
    if (($_GET['ajax'] ?? '') === '1') {
        $sendJson([
            'success' => false,
            'message' => 'Pencarian atau filter tidak valid. Silakan pilih nilai yang tersedia.',
            'data' => null,
            'errors' => [],
        ], 422);
    }
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
    $where[] = '(s.nis LIKE :search_nis OR s.nama LIKE :search_nama OR k.nama LIKE :search_kelas OR j.nama LIKE :search_jurusan)';
    $pattern = '%' . $search . '%';
    $parameters += ['search_nis' => $pattern, 'search_nama' => $pattern, 'search_kelas' => $pattern, 'search_jurusan' => $pattern];
}
if ($kelas !== '') {
    $where[] = 'k.nama = :kelas';
    $parameters['kelas'] = $kelas;
}
if ($jurusan !== '') {
    $where[] = 'j.nama = :jurusan';
    $parameters['jurusan'] = $jurusan;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$countStatement = $pdo->prepare('SELECT COUNT(*) FROM siswa s JOIN kelas k ON k.id=s.kelas_id JOIN jurusan j ON j.id=s.jurusan_id' . $whereSql);
$countStatement->execute($parameters);
$total = (int) $countStatement->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$sortColumnQualified = $sortColumn === 'nis' ? 's.nis' : 's.nama';
$listStatement = $pdo->prepare("SELECT s.id, s.nis, s.nama, k.nama AS kelas, j.nama AS jurusan, s.jenis_kelamin, s.no_telepon, s.foto FROM siswa s JOIN kelas k ON k.id=s.kelas_id JOIN jurusan j ON j.id=s.jurusan_id{$whereSql} ORDER BY {$sortColumnQualified} {$direction}, s.id ASC LIMIT :limit OFFSET :offset");
foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$students = $listStatement->fetchAll();

$renderStudentRows = function (array $items, int $startIndex, array $currentUser): string {
    ob_start();
    if ($items === []) {
        echo '<tr><td class="empty-state" colspan="9">Tidak ada data siswa yang cocok dengan filter.</td></tr>';
    } else {
        foreach ($items as $index => $student) {
            echo '<tr>';
            echo '<td>' . e($startIndex + $index + 1) . '</td>';
            echo '<td>' . (!empty($student['foto']) ? '<img src="../assets/foto/' . e(basename($student['foto'])) . '" alt="Foto siswa" class="student-photo-thumbnail">' : '<span class="student-photo-placeholder"><i class="bi bi-person"></i></span>') . '</td>';
            echo '<td>' . e($student['nis']) . '</td>';
            echo '<td><a class="table-primary-link" href="siswa-detail.php?id=' . e($student['id']) . '">' . e($student['nama']) . '</a></td>';
            echo '<td>' . e($student['kelas']) . '</td><td>' . e($student['jurusan']) . '</td>';
            echo '<td>' . ($student['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan') . '</td><td>' . e($student['no_telepon']) . '</td>';
            echo '<td><div class="table-actions">';
            echo '<a class="btn btn-sm btn-light" href="siswa-detail.php?id=' . e($student['id']) . '" aria-label="Detail siswa"><i class="bi bi-eye"></i></a>';
            if ($currentUser['role'] === 'admin') {
                echo '<a class="btn btn-sm btn-light" href="siswa-form.php?id=' . e($student['id']) . '" aria-label="Edit siswa" data-student-edit data-student-id="' . e($student['id']) . '"><i class="bi bi-pencil"></i></a>';
                echo '<form method="post" class="delete-form" data-confirm="Hapus data siswa ini? Tindakan ini tidak dapat dibatalkan." data-student-delete-form data-student-id="' . e($student['id']) . '">';
                echo csrf_field() . '<input type="hidden" name="id" value="' . e($student['id']) . '">';
                echo '<button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Hapus siswa"><i class="bi bi-trash"></i></button>';
                echo '</form>';
            }
            echo '</div></td>';
            echo '</tr>';
        }
    }
    return (string) ob_get_clean();
};

$renderPagination = static function (int $currentPage, int $pageCount): string {
    if ($pageCount <= 1) {
        return '';
    }

    $query = $_GET;
    unset($query['ajax']);
    ob_start();
    ?>
    <nav class="pagination-wrap" aria-label="Halaman daftar siswa">
        <span>Halaman <?= e($currentPage) ?> dari <?= e($pageCount) ?></span>
        <div class="btn-group">
            <?php foreach (array_filter([$currentPage > 1 ? $currentPage - 1 : null, $currentPage, $currentPage < $pageCount ? $currentPage + 1 : null]) as $pageNumber): ?>
                <?php $query['page'] = $pageNumber; ?>
                <a class="btn btn-sm <?= $pageNumber === $currentPage ? 'btn-primary' : 'btn-outline-primary' ?>" href="?<?= e(http_build_query($query)) ?>"><?= e($pageNumber) ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php
    return (string) ob_get_clean();
};

if (($_GET['ajax'] ?? '') === '1') {
    $sendJson([
        'success' => true,
        'message' => 'Daftar siswa berhasil dimuat.',
        'data' => [
            'rowsHtml' => $renderStudentRows($students, $offset, $user),
            'paginationHtml' => $renderPagination($page, $pages),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ],
        'errors' => [],
    ]);
}

render_header('Data Siswa', 'siswa.php', $user);
?>
<section class="panel">
    <div class="panel-heading"><div><h2>Daftar siswa</h2><p data-student-count><?= e($total) ?> data sesuai pencarian dan filter.</p></div>
        <?php if ($user['role'] === 'admin'): ?><a class="btn btn-primary" href="siswa-form.php" data-student-create><i class="bi bi-plus-lg"></i> Tambah siswa</a><?php endif; ?>
    </div>
    <form method="get" class="filter-form" data-student-filter>
        <label class="search-control"><i class="bi bi-search" aria-hidden="true"></i><input name="q" type="search" maxlength="100" value="<?= e($search) ?>" placeholder="Cari NIS, nama, kelas, atau jurusan" aria-label="Cari siswa berdasarkan NIS, nama, kelas, atau jurusan" data-student-search></label>
        <select name="kelas" class="form-select" aria-label="Filter kelas"><option value="">Semua kelas</option><?php foreach ($classes as $option): ?><option value="<?= e($option) ?>" <?= $kelas === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
        <select name="jurusan" class="form-select" aria-label="Filter jurusan"><option value="">Semua jurusan</option><?php foreach ($majors as $option): ?><option value="<?= e($option) ?>" <?= $jurusan === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
        <select name="sort" class="form-select" aria-label="Urutkan berdasarkan"><option value="nama" <?= $sortColumn === 'nama' ? 'selected' : '' ?>>Urut: nama</option><option value="nis" <?= $sortColumn === 'nis' ? 'selected' : '' ?>>Urut: NIS</option></select>
        <select name="direction" class="form-select" aria-label="Arah pengurutan"><option value="asc" <?= $direction === 'ASC' ? 'selected' : '' ?>>A–Z / naik</option><option value="desc" <?= $direction === 'DESC' ? 'selected' : '' ?>>Z–A / turun</option></select>
        <button class="btn btn-outline-primary" type="submit">Terapkan</button>
    </form>
    <div class="table-responsive">
        <table class="table align-middle data-table mb-0">
            <thead><tr><th>No.</th><th>Foto</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jurusan</th><th>Jenis kelamin</th><th>Telepon</th><th>Aksi</th></tr></thead>
            <tbody data-student-table>
            <?= $renderStudentRows($students, $offset, $user) ?>
            </tbody>
        </table>
    </div>
    <div data-student-pagination><?= $renderPagination($page, $pages) ?></div>
</section>
<dialog data-student-dialog aria-labelledby="studentDialogTitle">
    <form method="dialog" data-student-ajax-form>
        <div class="panel-heading"><div><h2 id="studentDialogTitle">Tambah siswa</h2><p>Lengkapi semua informasi dengan benar.</p></div><button class="btn btn-light" type="button" data-student-dialog-close><i class="bi bi-x-lg"></i> Tutup</button></div>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id" value="">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div><label for="ajax_nis" class="form-label">NIS</label><input id="ajax_nis" name="nis" class="form-control" data-student-field inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" title="Masukkan tepat 10 angka." required></div>
            <div><label for="ajax_nama" class="form-label">Nama lengkap</label><input id="ajax_nama" name="nama" autocomplete="name" class="form-control" data-student-field minlength="2" maxlength="100" required></div>
            <div><label for="ajax_kelas" class="form-label">Kelas</label><select id="ajax_kelas" name="kelas_id" class="form-select" data-student-field required><option value="">Pilih kelas</option><?php foreach ($classOptions as $option): ?><option value="<?= e($option['id']) ?>"><?= e($option['nama']) ?></option><?php endforeach; ?></select></div>
            <div><label for="ajax_jurusan" class="form-label">Jurusan</label><select id="ajax_jurusan" name="jurusan_id" class="form-select" data-student-field required><option value="">Pilih jurusan</option><?php foreach ($majorOptions as $option): ?><option value="<?= e($option['id']) ?>"><?= e($option['nama']) ?></option><?php endforeach; ?></select></div>
            <div><label for="ajax_jenis_kelamin" class="form-label">Jenis kelamin</label><select id="ajax_jenis_kelamin" name="jenis_kelamin" class="form-select" data-student-field required><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
            <div><label for="ajax_no_telepon" class="form-label">Nomor HP</label><input id="ajax_no_telepon" name="no_telepon" type="tel" autocomplete="tel" class="form-control" data-student-field inputmode="numeric" minlength="10" maxlength="13" pattern="[0-9]{10,13}" title="Masukkan 10-13 angka tanpa tanda baca." required></div>
            <div class="form-wide"><label for="ajax_alamat" class="form-label">Alamat</label><textarea id="ajax_alamat" name="alamat" class="form-control" data-student-field rows="4" minlength="5" maxlength="2000" required></textarea></div>
        </div>
        <div class="form-actions"><button class="btn btn-light" type="button" data-student-dialog-close>Batal</button><button class="btn btn-primary" type="submit">Simpan siswa</button></div>
    </form>
</dialog>
<?php render_footer(); ?>
