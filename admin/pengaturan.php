<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin', 'petugas', 'siswa']);
$pdo = db();
$errors = [];
$name = $user['nama'];
$email = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = normalize_text(input_string($_POST, 'nama'));
    $email = strtolower(trim(input_string($_POST, 'email')));
    $oldPassword = input_string($_POST, 'password_lama');
    $newPassword = input_string($_POST, 'password_baru');
    $confirmation = input_string($_POST, 'konfirmasi_password');

    if (!valid_person_name($name)) {
        $errors[] = 'Nama harus terdiri dari 2–100 karakter dan hanya berisi huruf, spasi, titik, apostrof, atau tanda hubung.';
    }
    if (!valid_email_address($email)) {
        $errors[] = 'Masukkan email yang valid (maksimal 100 karakter).';
    }
    $passwordFieldsProvided = $oldPassword !== '' || $newPassword !== '' || $confirmation !== '';
    if ($passwordFieldsProvided) {
        if ($oldPassword === '') {
            $errors[] = 'Masukkan password lama untuk mengganti password.';
        }
        if (!valid_password($newPassword)) {
            $errors[] = 'Password baru harus terdiri dari minimal 8 karakter dan maksimal 72 byte.';
        }
        if ($newPassword !== $confirmation) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        }
    }

    if ($errors === []) {
        try {
            $statement = $pdo->prepare('SELECT id, password FROM users WHERE id = :id');
            $statement->execute(['id' => $user['id']]);
            $account = $statement->fetch();
            if (!$account || ($passwordFieldsProvided && !password_verify($oldPassword, $account['password']))) {
                $errors[] = 'Password lama tidak sesuai.';
            } else {
                $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
                $duplicate->execute(['email' => $email, 'id' => $user['id']]);
                if ($duplicate->fetch()) {
                    $errors[] = 'Email tersebut sudah digunakan akun lain.';
                } else {
                    if ($passwordFieldsProvided) {
                        $update = $pdo->prepare('UPDATE users SET nama = :nama, email = :email, password = :password WHERE id = :id');
                        $update->execute(['nama' => $name, 'email' => $email, 'password' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $user['id']]);
                    } else {
                        $update = $pdo->prepare('UPDATE users SET nama = :nama, email = :email WHERE id = :id');
                        $update->execute(['nama' => $name, 'email' => $email, 'id' => $user['id']]);
                    }
                    set_flash('success', 'Pengaturan akun berhasil diperbarui.');
                    redirect('pengaturan.php');
                }
            }
        } catch (Throwable $error) {
            report_database_error($error);
            $errors[] = 'Perubahan akun gagal disimpan.';
        }
    }
}

render_header('Pengaturan Akun', 'pengaturan.php', $user);
?>
<section class="panel form-panel">
    <div class="panel-heading"><div><h2>Informasi akun</h2><p>Perbarui nama, email, atau password Anda.</p></div></div>
    <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="student-form">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div><label for="nama" class="form-label">Nama lengkap</label><input id="nama" name="nama" autocomplete="name" class="form-control" minlength="2" maxlength="100" required value="<?= e($name) ?>"></div>
            <div><label for="email" class="form-label">Email</label><input id="email" name="email" type="email" autocomplete="email" class="form-control" maxlength="100" required value="<?= e($email) ?>"></div>
            <div class="form-section-title form-wide"><h3>Ganti password <span>(opsional)</span></h3><p>Password lama diperlukan untuk menyimpan password baru.</p></div>
            <div>
                <label for="password_lama" class="form-label">Password lama</label>
                <div class="input-group">
                    <input id="password_lama" name="password_lama" type="password" class="form-control" maxlength="72" autocomplete="current-password" data-optional-password>
                    <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="password_lama" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <div>
                <label for="password_baru" class="form-label">Password baru</label>
                <div class="input-group">
                    <input id="password_baru" name="password_baru" type="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" data-optional-password>
                    <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="password_baru" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <div>
                <label for="konfirmasi_password" class="form-label">Konfirmasi password baru</label>
                <div class="input-group">
                    <input id="konfirmasi_password" name="konfirmasi_password" type="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" data-optional-password data-match="password_baru" data-match-message="Konfirmasi password baru harus sama dengan password baru.">
                    <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="konfirmasi_password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                </div>
            </div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Simpan pengaturan</button></div>
    </form>
</section>
<?php render_footer(); ?>
