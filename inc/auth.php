<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Permintaan tidak valid. Muat ulang halaman dan coba lagi.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId || $userId < 1) {
        return null;
    }

    $statement = db()->prepare('SELECT id, nama, email, role, siswa_id FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch() ?: null;

    if ($user === null) {
        unset($_SESSION['user_id']);
    }

    return $user;
}

function missing_schema_tables(): array
{
    $statement = db()->query(
        "SELECT table_name FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name IN ('users', 'siswa')"
    );
    $existing = $statement->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_diff(['users', 'siswa'], $existing));
}

function missing_schema_requirements(): array
{
    $missing = missing_schema_tables();
    if ($missing !== []) {
        return $missing;
    }

    $columns = db()->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'users'
           AND column_name IN ('role', 'siswa_id')"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('siswa_id', $columns, true)) {
        $missing[] = 'kolom users.siswa_id (impor migration-roles.sql)';
    }

    $roleDefinition = db()->query(
        "SELECT column_type FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'"
    )->fetchColumn();
    foreach (['admin', 'petugas', 'siswa'] as $role) {
        if (!is_string($roleDefinition) || !str_contains($roleDefinition, "'" . $role . "'")) {
            $missing[] = "role {$role} (impor migration-roles.sql)";
        }
    }

    return $missing;
}

function require_auth(array $roles = []): array
{
    $missingRequirements = missing_schema_requirements();
    if ($missingRequirements !== []) {
        $inAdminDirectory = basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin';
        redirect($inAdminDirectory ? '../database-setup.php' : 'database-setup.php');
    }

    $user = current_user();
    if ($user === null) {
        set_flash('warning', 'Silakan masuk terlebih dahulu.');
        $inAdminDirectory = basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin';
        redirect($inAdminDirectory ? '../index.php' : 'index.php');
    }

    if ($roles !== [] && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Anda tidak memiliki izin untuk mengakses halaman ini.');
    }

    return $user;
}

function normalize_text(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}

function input_string(array $input, string $key): string
{
    $value = $input[$key] ?? '';

    return is_string($value) ? $value : '';
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function valid_person_name(string $value): bool
{
    return text_length($value) >= 2
        && text_length($value) <= 100
        && preg_match("/^[\\p{L}\\p{M}]+(?:[ '-][\\p{L}\\p{M}]+|\\. ?[\\p{L}\\p{M}]+)*$/u", $value) === 1;
}

function valid_email_address(string $value): bool
{
    return text_length($value) <= 100
        && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_password(string $value): bool
{
    return text_length($value) >= 8 && strlen($value) <= 72;
}

function valid_student_identifier(string $value): bool
{
    return preg_match('/^[0-9]{10}$/D', $value) === 1;
}

function valid_school_label(string $value, int $maximumLength, bool $allowLabelPunctuation = false): bool
{
    $pattern = $allowLabelPunctuation
        ? "/^[\\p{L}\\p{N}][\\p{L}\\p{M}\\p{N} .&\\/()'-]*$/u"
        : "/^[\\p{L}\\p{N}][\\p{L}\\p{M}\\p{N} .-]*$/u";

    return $value !== ''
        && text_length($value) <= $maximumLength
        && preg_match($pattern, $value) === 1;
}

function normalize_phone_number(string $value): string
{
    return preg_replace('/[\s().-]+/u', '', trim($value)) ?? '';
}

function valid_phone_number(string $value): bool
{
    return preg_match('/^[0-9]{10,13}$/D', $value) === 1;
}

function valid_postal_address(string $value): bool
{
    return text_length($value) >= 5
        && text_length($value) <= 2000
        && preg_match('/[\p{L}\p{N}]/u', $value) === 1
        && preg_match('/[<>]/u', $value) !== 1
        && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) !== 1;
}

function report_database_error(Throwable $error): void
{
    error_log($error->__toString());
    set_flash('danger', 'Terjadi kesalahan saat mengakses database. Periksa konfigurasi dan coba lagi.');
}
