<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input_string($_POST, 'action');

    if ($action === 'create_petugas') {
        $name = normalize_text(input_string($_POST, 'nama'));
        $email = strtolower(trim(input_string($_POST, 'email')));
        $password = input_string($_POST, 'password');
        $confirmation = input_string($_POST, 'password_confirmation');

        if (!valid_person_name($name) || !valid_email_address($email) || !valid_password($password) || $password !== $confirmation) {
            set_flash('danger', 'Data petugas tidak valid. Periksa nama, email, password minimal 8 karakter, dan konfirmasinya.');
        } else {
            try {
                $statement = $pdo->prepare("INSERT INTO users (nama, email, password, role) VALUES (:nama, :email, :password, 'petugas')");
                $statement->execute([
                    'nama' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                set_flash('success', 'Akun petugas berhasil dibuat.');
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    set_flash('danger', 'Email tersebut sudah digunakan akun lain.');
                } else {
                    report_database_error($error);
                }
            }
        }
    } elseif (!in_array($action, ['delete', 'role'], true)) {
        set_flash('danger', 'Aksi tidak dikenali.');
    } else {
        $targetId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$targetId || $targetId < 1) {
            set_flash('danger', 'ID pengguna tidak valid.');
            redirect('users.php');
        }
        if ($targetId === (int) $user['id']) {
            set_flash('warning', 'Anda tidak dapat menghapus akun sendiri atau mengubah role akun sendiri.');
            redirect('users.php');
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
                set_flash('warning', 'Pengguna tidak ditemukan.');
            } elseif ($action === 'delete') {
                if ($target['role'] === 'admin' && $adminCount <= 1) {
                    $pdo->rollBack();
                    set_flash('warning', 'Admin terakhir tidak dapat dihapus.');
                } else {
                    $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $statement->execute(['id' => $targetId]);
                    $pdo->commit();
                    set_flash('success', 'Pengguna berhasil dihapus.');
                }
            } else {
                $newRole = input_string($_POST, 'role');
                if (!in_array($newRole, ['admin', 'petugas', 'siswa'], true)) {
                    $pdo->rollBack();
                    set_flash('danger', 'Role yang dipilih tidak valid.');
                } elseif ($target['role'] === 'admin' && $newRole !== 'admin' && $adminCount <= 1) {
                    $pdo->rollBack();
                    set_flash('warning', 'Role admin terakhir tidak dapat diubah.');
                } else {
                    $studentId = null;
                    if ($newRole === 'siswa') {
                        $studentId = filter_var($_POST['siswa_id'] ?? null, FILTER_VALIDATE_INT);
                        if (!$studentId || $studentId < 1) {
                            $pdo->rollBack();
                            set_flash('danger', 'Pilih data siswa yang akan ditautkan ke akun ini.');
                            redirect('users.php');
                        }

                        $student = $pdo->prepare('SELECT id FROM siswa WHERE id = :id FOR UPDATE');
                        $student->execute(['id' => $studentId]);
                        if (!$student->fetch()) {
                            $pdo->rollBack();
                            set_flash('danger', 'Data siswa yang dipilih tidak ditemukan.');
                            redirect('users.php');
                        }

                        $linked = $pdo->prepare('SELECT id FROM users WHERE siswa_id = :siswa_id AND id <> :id');
                        $linked->execute(['siswa_id' => $studentId, 'id' => $targetId]);
                        if ($linked->fetch()) {
                            $pdo->rollBack();
                            set_flash('danger', 'Data siswa tersebut sudah ditautkan ke akun lain.');
                            redirect('users.php');
                        }
                    }

                    try {
                        $statement = $pdo->prepare('UPDATE users SET role = :role, siswa_id = :siswa_id WHERE id = :id');
                        $statement->execute(['role' => $newRole, 'siswa_id' => $studentId, 'id' => $targetId]);
                        $pdo->commit();
                        set_flash('success', 'Role pengguna berhasil diperbarui.');
                    } catch (PDOException $error) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if ($error->getCode() === '23000') {
                            set_flash('danger', 'NIS tersebut sudah terhubung ke akun lain.');
                        } else {
                            throw $error;
                        }
                    }
                }
            }
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            report_database_error($error);
        }
    }

    redirect('users.php');
}

$search = normalize_text(input_string($_GET, 'q'));
if ((isset($_GET['q']) && !is_string($_GET['q'])) || text_length($search) > 100) {
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

render_header('Data Pengguna', 'users.php', $user);
?>
<section class="panel">
    <div class="panel-heading"><div><h2><i class="bi bi-person-plus me-2"></i>Tambah petugas</h2><p>Buat akun baru dengan akses hanya-baca ke data siswa.</p></div></div>
    <form method="post" class="form-grid create-user-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_petugas">
        <div><label class="form-label" for="petugas_nama">Nama petugas</label><input id="petugas_nama" name="nama" class="form-control" minlength="2" maxlength="100" required></div>
        <div><label class="form-label" for="petugas_email">Email</label><input id="petugas_email" name="email" type="email" class="form-control" maxlength="100" required></div>
        <div><label class="form-label" for="petugas_password">Password (minimal 8 karakter)</label><div class="input-group"><input id="petugas_password" name="password" type="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required><button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="petugas_password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button></div></div>
        <div><label class="form-label" for="petugas_confirmation">Konfirmasi password</label><div class="input-group"><input id="petugas_confirmation" name="password_confirmation" type="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required><button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="petugas_confirmation" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button></div></div>
        <div class="form-wide text-end"><button class="btn btn-primary" type="submit"><i class="bi bi-person-plus me-1"></i>Buat akun petugas</button></div>
    </form>
</section>
<section class="panel">
    <div class="panel-heading"><div><h2>Kelola pengguna</h2><p><?= e(count($users)) ?> pengguna ditemukan. Akun Anda sendiri tidak dapat dikelola dari sini.</p></div></div>
    <form method="get" class="filter-form user-search"><label class="search-control"><i class="bi bi-search"></i><input name="q" type="search" maxlength="100" value="<?= e($search) ?>" placeholder="Cari nama atau email"></label><button class="btn btn-outline-primary" type="submit">Cari</button></form>
    <div class="table-responsive">
        <table class="table align-middle data-table mb-0">
            <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>NIS tertaut</th><th>Terdaftar</th><th>Ubah role</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if ($users === []): ?><tr><td class="empty-state" colspan="7">Pengguna tidak ditemukan.</td></tr>
            <?php else: foreach ($users as $account): ?>
                <tr>
                    <td><?= e($account['nama']) ?><?= (int) $account['id'] === (int) $user['id'] ? ' (Anda)' : '' ?></td>
                    <td><?= e($account['email']) ?></td><td><span class="role-badge <?= e($account['role']) ?>"><?= e(ucfirst($account['role'])) ?></span></td>
                    <td><?php if ($account['nis'] !== null): ?><?= e($account['nis']) ?><?php elseif ($account['role'] === 'siswa'): ?><span class="text-warning">Belum ditautkan</span><?php else: ?>—<?php endif; ?></td>
                    <td><?= e(date('d/m/Y', strtotime($account['created_at']))) ?></td>
                    <td>
                        <?php if ((int) $account['id'] !== (int) $user['id']): ?>
                            <form method="post" class="inline-form" data-role-form><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= e($account['id']) ?>">
                                <select class="form-select form-select-sm" name="role" aria-label="Role untuk <?= e($account['nama']) ?>" data-role-select><option value="siswa" <?= $account['role'] === 'siswa' ? 'selected' : '' ?>>Siswa</option><option value="petugas" <?= $account['role'] === 'petugas' ? 'selected' : '' ?>>Petugas</option><option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select>
                                <select class="form-select form-select-sm" name="siswa_id" aria-label="Data siswa untuk <?= e($account['nama']) ?>" data-student-select <?= $account['role'] === 'siswa' ? 'required' : 'hidden' ?>><option value="">Pilih NIS</option><?php foreach ($students as $student): ?><option value="<?= e($student['id']) ?>" <?= (int) $account['siswa_id'] === (int) $student['id'] ? 'selected' : '' ?>><?= e($student['nis'] . ' — ' . $student['nama']) ?></option><?php endforeach; ?></select>
                                <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                            </form>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td><?php if ((int) $account['id'] !== (int) $user['id']): ?>
                        <form method="post" class="delete-form" data-confirm="Hapus akun pengguna ini? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($account['id']) ?>"><button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Hapus akun"><i class="bi bi-trash"></i></button></form>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
