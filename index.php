<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth-layout.php';

try {
    $user = current_user();
    if ($user !== null) {
        redirect('admin/dashboard.php');
    }
} catch (Throwable $error) {
    error_log($error->__toString());
    unset($_SESSION['user_id']);
    set_flash('danger', 'Database belum siap. Periksa konfigurasi dan jalankan database.sql.');
}

$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim(input_string($_POST, 'email')));
    $password = input_string($_POST, 'password');

    if (!valid_email_address($email) || $password === '' || strlen($password) > 72) {
        $errorMessage = 'Email atau password salah.';
    } else {
        try {
            $statement = db()->prepare('SELECT id, password FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
            $account = $statement->fetch();

            if ($account && password_verify($password, $account['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $account['id'];
                unset($_SESSION['csrf_token']);
                redirect('admin/dashboard.php');
            }
            $errorMessage = 'Email atau password salah.';
        } catch (Throwable $error) {
            error_log($error->__toString());
            $errorMessage = 'Database belum siap. Periksa konfigurasi dan jalankan database.sql.';
        }
    }
}

render_auth_start('Masuk');
?>
<p class="eyebrow">SELAMAT DATANG KEMBALI</p>
<h2 class="auth-title">Masuk ke akun</h2>
<p class="auth-description">Gunakan email dan password akun Anda untuk melanjutkan.</p>
<?php if ($errorMessage !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= e($errorMessage) ?></div>
<?php endif; ?>
<form method="post" class="auth-form">
    <?= csrf_field() ?>
    <label for="email" class="form-label">Email</label>
    <input id="email" name="email" type="email" class="form-control" autocomplete="email" maxlength="100" required value="<?= e(input_string($_POST, 'email')) ?>">
    <label for="password" class="form-label mt-3">Password</label>
    <div class="input-group">
        <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" maxlength="72" required>
        <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle="password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
    </div>
    <button class="btn btn-primary w-100 auth-submit" type="submit">Masuk <i class="bi bi-arrow-right ms-1"></i></button>
</form>
<p class="auth-switch">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
<?php render_auth_end(); ?>
