<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth-layout.php';

try {
    if (current_user() !== null) {
        redirect('admin/dashboard.php');
    }
} catch (Throwable $error) {
    error_log($error->__toString());
}

$errors = [];
$values = ['nis' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values['nis'] = trim(input_string($_POST, 'nis'));
    $values['email'] = strtolower(trim(input_string($_POST, 'email')));
    $password = input_string($_POST, 'password');
    $confirmation = input_string($_POST, 'password_confirmation');

    if (!valid_student_identifier($values['nis'])) {
        $errors[] = 'NIS harus terdiri tepat dari 10 angka.';
    }
    if (!valid_email_address($values['email'])) {
        $errors[] = 'Masukkan alamat email yang valid (maksimal 100 karakter).';
    }
    if (!valid_password($password)) {
        $errors[] = 'Password harus terdiri dari minimal 8 karakter dan maksimal 72 byte.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if ($errors === []) {
        try {
            $pdo = db();
            $studentStatement = $pdo->prepare('SELECT id, nama FROM siswa WHERE nis = :nis');
            $studentStatement->execute(['nis' => $values['nis']]);
            $student = $studentStatement->fetch();
            if (!$student) {
                $errors[] = 'NIS belum terdaftar. Minta admin menambahkan data siswa melalui menu Data Siswa terlebih dahulu.';
            } else {
                $linkedAccount = $pdo->prepare('SELECT id FROM users WHERE siswa_id = :siswa_id');
                $linkedAccount->execute(['siswa_id' => $student['id']]);
                if ($linkedAccount->fetch()) {
                    $errors[] = 'NIS tersebut sudah terhubung ke akun lain.';
                }
            }

            if ($errors !== []) {
                throw new DomainException('Validasi hubungan akun siswa gagal.');
            }

            $statement = $pdo->prepare('INSERT INTO users (nama, email, password, role, siswa_id) VALUES (:nama, :email, :password, :role, :siswa_id)');
            $statement->execute([
                'nama' => $student['nama'],
                'email' => $values['email'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'siswa',
                'siswa_id' => $student['id'],
            ]);
            set_flash('success', 'Pendaftaran berhasil. Silakan masuk dengan akun baru.');
            redirect('index.php');
        } catch (DomainException) {
            // Validation messages above are shown without creating an account.
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                $errors[] = 'Email atau NIS tersebut sudah terhubung ke akun lain.';
            } else {
                error_log($error->__toString());
                $errors[] = 'Pendaftaran gagal karena masalah database. Coba lagi nanti.';
            }
        }
    }
}

render_auth_start('Daftar');
?>
<p class="eyebrow">MULAI MENGELOLA DATA</p>
<h2 class="auth-title">Buat akun</h2>
<p class="auth-description">Sebelum mendaftar, pastikan admin sudah menambahkan data Anda melalui menu Data Siswa. Gunakan NIS tepat 10 digit yang terdaftar; akun siswa hanya dapat melihat profil sendiri.</p>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<form method="post" class="auth-form">
    <?= csrf_field() ?>
    <label for="nis" class="form-label">NIS</label>
    <input id="nis" name="nis" type="text" class="form-control" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" title="Masukkan NIS yang sudah terdaftar, tepat 10 angka." required value="<?= e($values['nis']) ?>">
    <label for="email" class="form-label mt-3">Email</label>
    <input id="email" name="email" type="email" class="form-control" autocomplete="email" maxlength="100" required value="<?= e($values['email']) ?>">
    <label for="password" class="form-label mt-3">Password</label>
    <div class="input-group">
        <input id="password" name="password" type="password" class="form-control" autocomplete="new-password" minlength="8" maxlength="72" required>
        <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
    </div>
    <label for="password_confirmation" class="form-label mt-3">Konfirmasi password</label>
    <div class="input-group">
        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" minlength="8" maxlength="72" required>
        <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
    </div>
    <button class="btn btn-primary w-100 auth-submit" type="submit">Daftar <i class="bi bi-arrow-right ms-1"></i></button>
</form>
<p class="auth-switch">Sudah punya akun? <a href="index.php">Login</a></p>
<?php render_auth_end(); ?>
