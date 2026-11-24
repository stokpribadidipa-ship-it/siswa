<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin']);
$pdo = db();
$isAjaxRequest = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$finishRequest = static function (string $type, string $message, int $status = 200) use ($isAjaxRequest): never {
    if ($isAjaxRequest) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $type === 'success',
            'message' => $message,
            'data' => null,
            'errors' => [],
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    set_flash($type, $message);
    redirect('users.php');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAjaxRequest) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!is_string($csrfToken) || !hash_equals(csrf_token(), $csrfToken)) {
            $finishRequest('danger', 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.', 400);
        }
    } else {
        verify_csrf();
    }

    $action = input_string($_POST, 'action');

    if ($action === 'create_petugas') {
        $name = normalize_text(input_string($_POST, 'nama'));
        $email = strtolower(trim(input_string($_POST, 'email')));
        $password = input_string($_POST, 'password');
        $confirmation = input_string($_POST, 'password_confirmation');

        if (!valid_person_name($name) || !valid_email_address($email) || !valid_password($password) || $password !== $confirmation) {
            $finishRequest('danger', 'Data petugas tidak valid. Periksa nama, email, password minimal 8 karakter, dan konfirmasinya.', 422);
        }

        try {
            $statement = $pdo->prepare("INSERT INTO users (nama, email, password, role) VALUES (:nama, :email, :password, 'petugas')");
            $statement->execute([
                'nama' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                $finishRequest('danger', 'Email tersebut sudah digunakan akun lain.', 422);
            }
            report_database_error($error);
            $finishRequest('danger', 'Akun petugas gagal dibuat. Coba lagi nanti.', 500);
        }
        $finishRequest('success', 'Akun petugas berhasil dibuat.');
    }

    if (!in_array($action, ['delete', 'role'], true)) {
        $finishRequest('danger', 'Aksi tidak dikenali.', 400);
    }

    $targetId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$targetId || $targetId < 1) {
        $finishRequest('danger', 'ID pengguna tidak valid.', 400);
    }
    if ($targetId === (int) $user['id']) {
        $finishRequest('warning', 'Anda tidak dapat menghapus akun sendiri atau mengubah role akun sendiri.', 422);
    }

    try {
        $pdo->beginTransaction();
        $accounts = $pdo->query('SELECT id, role, siswa_id FROM users FOR UPDATE')->fetchAll();
        $target = null;
        $adminCount = 0;
        foreach ($accounts as $account) {
            if ($account['role'] === 'admin') {
                $adminCount++;
            }
            if ((int) $account['id'] === $targetId) {
                $target = $account;
            }
        }

        if ($target === null) {
            $pdo->rollBack();
            $finishRequest('warning', 'Pengguna tidak ditemukan.', 404);
        }

        if ($action === 'delete') {
            if ($target['role'] === 'admin' && $adminCount <= 1) {
                $pdo->rollBack();
                $finishRequest('warning', 'Admin terakhir tidak dapat dihapus.', 422);
            }
            $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute(['id' => $targetId]);
            $pdo->commit();
            $finishRequest('success', 'Pengguna berhasil dihapus.');
        }

        $newRole = input_string($_POST, 'role');
        if (!in_array($newRole, ['admin', 'petugas', 'siswa'], true)) {
            $pdo->rollBack();
            $finishRequest('danger', 'Role yang dipilih tidak valid.', 422);
        }
        if ($target['role'] === 'admin' && $newRole !== 'admin' && $adminCount <= 1) {
            $pdo->rollBack();
            $finishRequest('warning', 'Role admin terakhir tidak dapat diubah.', 422);
        }

        $studentId = null;
        if ($newRole === 'siswa') {
            $studentId = filter_var($_POST['siswa_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$studentId || $studentId < 1) {
                $pdo->rollBack();
                $finishRequest('danger', 'Pilih data siswa yang akan ditautkan ke akun ini.', 422);
            }

            $student = $pdo->prepare('SELECT id FROM siswa WHERE id = :id FOR UPDATE');
            $student->execute(['id' => $studentId]);
            if (!$student->fetch()) {
                $pdo->rollBack();
                $finishRequest('danger', 'Data siswa yang dipilih tidak ditemukan.', 404);
            }

            $linked = $pdo->prepare('SELECT id FROM users WHERE siswa_id = :siswa_id AND id <> :id');
            $linked->execute(['siswa_id' => $studentId, 'id' => $targetId]);
            if ($linked->fetch()) {
                $pdo->rollBack();
                $finishRequest('danger', 'Data siswa tersebut sudah ditautkan ke akun lain.', 422);
            }
        }

        try {
            $statement = $pdo->prepare('UPDATE users SET role = :role, siswa_id = :siswa_id WHERE id = :id');
            $statement->execute(['role' => $newRole, 'siswa_id' => $studentId, 'id' => $targetId]);
            $pdo->commit();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error->getCode() === '23000') {
                $finishRequest('danger', 'NIS tersebut sudah terhubung ke akun lain.', 422);
            }
            throw $error;
        }
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        report_database_error($error);
        $finishRequest('danger', 'Perubahan pengguna gagal. Coba lagi nanti.', 500);
    }

    $finishRequest('success', 'Role pengguna berhasil diperbarui.');
}

$search = normalize_text(input_string($_GET, 'q'));
if ((isset($_GET['q']) && !is_string($_GET['q'])) || text_length($search) > 100) {
    if (($_GET['ajax'] ?? '') === '1') {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Pencarian tidak valid. Maksimal 100 karakter.',
            'data' => null,
            'errors' => [],
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    set_flash('warning', 'Pencarian tidak valid. Maksimal 100 karakter.');
    redirect('users.php');
}
if ($search === '') {
    $statement = $pdo->query('SELECT u.id, u.nama, u.email, u.role, u.siswa_id, s.nis, u.created_at FROM users u LEFT JOIN siswa s ON s.id = u.siswa_id ORDER BY u.nama, u.id');
    $users = $statement->fetchAll();
} else {
    $statement = $pdo->prepare('SELECT u.id, u.nama, u.email, u.role, u.siswa_id, s.nis, u.created_at FROM users u LEFT JOIN siswa s ON s.id = u.siswa_id WHERE u.nama LIKE :nama OR u.email LIKE :email ORDER BY u.nama, u.id');
    $statement->execute(['nama' => '%' . $search . '%', 'email' => '%' . $search . '%']);
    $users = $statement->fetchAll();
}
$students = $pdo->query('SELECT id, nis, nama FROM siswa ORDER BY nama')->fetchAll();
$renderUserRows = static function (array $accounts, array $students, array $currentUser): string {
    ob_start();
    if ($accounts === []) {
        echo '<tr><td class="empty-state" colspan="7">Pengguna tidak ditemukan.</td></tr>';
    } else {
        foreach ($accounts as $account) {
            ?>
            <tr>
                <td><?= e($account['nama']) ?><?= (int) $account['id'] === (int) $currentUser['id'] ? ' (Anda)' : '' ?></td>
                <td><?= e($account['email']) ?></td>
                <td><span class="role-badge <?= e($account['role']) ?>"><?= e(ucfirst($account['role'])) ?></span></td>
                <td><?php if ($account['nis'] !== null): ?><?= e($account['nis']) ?><?php elseif ($account['role'] === 'siswa'): ?><span class="text-warning">Belum ditautkan</span><?php else: ?>—<?php endif; ?></td>
                <td><?= e(date('d/m/Y', strtotime($account['created_at']))) ?></td>
                <td>
                    <?php if ((int) $account['id'] !== (int) $currentUser['id']): ?>
                        <form method="post" class="inline-form" data-role-form data-user-ajax-form>
                            <?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= e($account['id']) ?>">
                            <div class="inline-role-fields">
                                <select class="form-select form-select-sm" name="role" aria-label="Role untuk <?= e($account['nama']) ?>" data-role-select><option value="siswa" <?= $account['role'] === 'siswa' ? 'selected' : '' ?>>Siswa</option><option value="petugas" <?= $account['role'] === 'petugas' ? 'selected' : '' ?>>Petugas</option><option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select>
                                <select class="form-select form-select-sm" name="siswa_id" aria-label="Data siswa untuk <?= e($account['nama']) ?>" data-student-select <?= $account['role'] === 'siswa' ? 'required' : 'hidden' ?>><option value="">Pilih NIS</option><?php foreach ($students as $student): ?><option value="<?= e($student['id']) ?>" <?= (int) $account['siswa_id'] === (int) $student['id'] ? 'selected' : '' ?>><?= e($student['nis'] . ' — ' . $student['nama']) ?></option><?php endforeach; ?></select>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                        </form>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $account['id'] !== (int) $currentUser['id']): ?>
                        <form method="post" class="delete-form" data-confirm="Hapus akun pengguna ini? Tindakan ini tidak dapat dibatalkan." data-user-delete-form>
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($account['id']) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Hapus akun"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
            </tr>
            <?php
        }
    }
    return (string) ob_get_clean();
};

if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Daftar pengguna berhasil dimuat.',
        'data' => [
            'rowsHtml' => $renderUserRows($users, $students, $user),
            'total' => count($users),
        ],
        'errors' => [],
    ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

render_header('Data Pengguna', 'users.php', $user);
?>
<section class="panel">
    <div class="panel-heading"><div><h2><i class="bi bi-person-plus me-2"></i>Tambah petugas</h2><p>Buat akun baru dengan akses hanya-baca ke data siswa.</p></div></div>
    <form method="post" class="form-grid create-user-form" data-user-ajax-form>
        <?= csrf_field() ?><input type="hidden" name="action" value="create_petugas">
        <div><label class="form-label" for="petugas_nama">Nama petugas</label><input id="petugas_nama" name="nama" autocomplete="name" class="form-control" minlength="2" maxlength="100" required></div>
        <div><label class="form-label" for="petugas_email">Email</label><input id="petugas_email" name="email" type="email" autocomplete="email" class="form-control" maxlength="100" required></div>
        <div><label class="form-label" for="petugas_password">Password</label><div class="input-group"><input id="petugas_password" name="password" type="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="petugas-password-help" required><button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="petugas_password" aria-label="Tampilkan password"><i class="bi bi-eye" aria-hidden="true"></i></button></div><small id="petugas-password-help" class="form-text text-muted">Minimal 8 karakter.</small></div>
        <div><label class="form-label" for="petugas_confirmation">Konfirmasi password</label><div class="input-group"><input id="petugas_confirmation" name="password_confirmation" type="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required data-match="petugas_password"><button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="petugas_confirmation" aria-label="Tampilkan password"><i class="bi bi-eye" aria-hidden="true"></i></button></div></div>
        <div class="form-wide text-end"><button class="btn btn-primary" type="submit"><i class="bi bi-person-plus me-1"></i>Buat akun petugas</button></div>
    </form>
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Kelola pengguna</h2><p><span data-user-count><?= e(count($users)) ?> pengguna ditemukan.</span> Akun Anda sendiri tidak dapat dikelola dari sini.</p></div></div>
    <form method="get" class="filter-form user-search" data-user-filter><label class="search-control"><i class="bi bi-search" aria-hidden="true"></i><input name="q" type="search" maxlength="100" value="<?= e($search) ?>" placeholder="Cari nama atau email" aria-label="Cari pengguna berdasarkan nama atau email" data-user-search></label><button class="btn btn-outline-primary" type="submit">Cari</button></form>
    <div class="table-responsive">
        <table class="table align-middle data-table mb-0">
            <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>NIS tertaut</th><th>Terdaftar</th><th>Ubah role</th><th>Aksi</th></tr></thead>
            <tbody data-user-table>
            <?= $renderUserRows($users, $students, $user) ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
