<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/database.php';

if ($argc !== 4) {
    fwrite(STDERR, "Penggunaan: php setup-admin.php \"Nama Admin\" admin@example.com \"password-minimal-8\"\n");
    exit(1);
}

[$script, $name, $email, $password] = $argv;
$name = trim($name);

if (
    preg_match("/^[\\p{L}\\p{M}]+(?:[ '-][\\p{L}\\p{M}]+|\\. ?[\\p{L}\\p{M}]+)*$/u", $name) !== 1
    || mb_strlen($name, 'UTF-8') < 2
    || mb_strlen($name, 'UTF-8') > 100
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || mb_strlen($email, 'UTF-8') > 100
    || mb_strlen($password, 'UTF-8') < 8
    || strlen($password) > 72
) {
    fwrite(STDERR, "Nama/email tidak valid atau password harus minimal 8 karakter dan maksimal 72 byte.\n");
    exit(1);
}

try {
    $statement = db()->prepare('INSERT INTO users (nama, email, password, role) VALUES (:nama, :email, :password, :role)');
    $statement->execute([
        'nama' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
    fwrite(STDOUT, "Akun admin berhasil dibuat untuk {$email}.\n");
} catch (PDOException $error) {
    if ($error->getCode() === '23000') {
        fwrite(STDERR, "Email tersebut sudah digunakan.\n");
        exit(1);
    }
    error_log($error->__toString());
    fwrite(STDERR, "Akun admin gagal dibuat. Periksa koneksi database.\n");
    exit(1);
}
