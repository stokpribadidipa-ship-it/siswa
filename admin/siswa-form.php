<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/layout.php';
$user = require_auth(['admin']);
$pdo = db();
$rawId = $_GET['id'] ?? $_POST['id'] ?? null;
$id = $rawId === null ? null : filter_var($rawId, FILTER_VALIDATE_INT);
if ($rawId !== null && (!$id || $id < 1)) {
    http_response_code(400);
    exit('ID siswa tidak valid.');
}
$editing = $id !== false && $id !== null && $id > 0;
$student = ['nis' => '', 'nama' => '', 'kelas' => '', 'jurusan' => '', 'alamat' => '', 'jenis_kelamin' => 'L', 'no_telepon' => ''];
$errors = [];

if ($editing) {
    $statement = $pdo->prepare('SELECT nis, nama, kelas, jurusan, alamat, jenis_kelamin, no_telepon FROM siswa WHERE id = :id');
    $statement->execute(['id' => $id]);
    $found = $statement->fetch();
    if (!$found) {
        http_response_code(404);
        set_flash('warning', 'Data siswa tidak ditemukan.');
        redirect('siswa.php');
    }
    $student = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($student) as $field) {
        $student[$field] = input_string($_POST, $field);
    }
    $student['nis'] = trim($student['nis']);
    $student['nama'] = normalize_text($student['nama']);
    $student['kelas'] = normalize_text($student['kelas']);
    $student['jurusan'] = normalize_text($student['jurusan']);
    $student['no_telepon'] = normalize_phone_number($student['no_telepon']);
    $student['alamat'] = trim($student['alamat']);

    if (!valid_student_identifier($student['nis'])) {
        $errors[] = 'NIS wajib diisi tepat 10 angka.';
    }
    if (!valid_person_name($student['nama'])) {
        $errors[] = 'Nama harus terdiri dari 2–100 karakter dan hanya berisi huruf, spasi, titik, apostrof, atau tanda hubung.';
    }
    if (!valid_school_label($student['kelas'], 20)) {
        $errors[] = 'Kelas wajib diisi (maksimal 20 karakter); gunakan huruf, angka, spasi, titik, atau tanda hubung.';
    }
    if (!valid_school_label($student['jurusan'], 50, true)) {
        $errors[] = 'Jurusan wajib diisi (maksimal 50 karakter); gunakan huruf, angka, spasi, titik, garis miring, kurung, atau tanda hubung.';
    }
    if (!valid_phone_number($student['no_telepon'])) {
        $errors[] = 'Nomor HP harus berisi 10–13 angka.';
    }
    if (!valid_postal_address($student['alamat'])) {
        $errors[] = 'Alamat wajib diisi, panjangnya 5–2000 karakter, dan tidak boleh berisi tag HTML atau karakter kontrol.';
    }
    if (!in_array($student['jenis_kelamin'], ['L', 'P'], true)) {
        $errors[] = 'Pilih jenis kelamin yang valid.';
    }

    if ($errors === []) {
        $duplicate = $pdo->prepare('SELECT id FROM siswa WHERE nis = :nis AND id <> :id LIMIT 1');
        $duplicate->execute(['nis' => $student['nis'], 'id' => $editing ? $id : 0]);
        if ($duplicate->fetch()) {
            $errors[] = 'NIS tersebut sudah digunakan siswa lain.';
        } else {
            try {
                if ($editing) {
                    $statement = $pdo->prepare('UPDATE siswa SET nis = :nis, nama = :nama, kelas = :kelas, jurusan = :jurusan, alamat = :alamat, jenis_kelamin = :jenis_kelamin, no_telepon = :no_telepon WHERE id = :id');
                    $student['id'] = $id;
                    $statement->execute($student);
                } else {
                    $statement = $pdo->prepare('INSERT INTO siswa (nis, nama, kelas, jurusan, alamat, jenis_kelamin, no_telepon) VALUES (:nis, :nama, :kelas, :jurusan, :alamat, :jenis_kelamin, :no_telepon)');
                    $statement->execute($student);
                }
                set_flash('success', $editing ? 'Data siswa berhasil diperbarui.' : 'Data siswa berhasil ditambahkan.');
                redirect('siswa.php');
            } catch (Throwable $error) {
                report_database_error($error);
                $errors[] = 'Data gagal disimpan. Periksa kembali isian Anda.';
            }
        }
    }
}

render_header($editing ? 'Edit Siswa' : 'Tambah Siswa', 'siswa-form.php', $user);
?>
<section class="panel form-panel">
    <div class="panel-heading"><div><h2><?= $editing ? 'Perbarui data siswa' : 'Tambah siswa baru' ?></h2><p>Lengkapi semua informasi dengan benar.</p></div><a class="btn btn-outline-primary" href="siswa.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
    <?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="student-form">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= e($id) ?>"><?php endif; ?>
        <div class="form-grid">
            <div><label for="nis" class="form-label">NIS</label><input id="nis" name="nis" class="form-control" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" title="Masukkan tepat 10 angka." required value="<?= e($student['nis']) ?>"></div>
            <div><label for="nama" class="form-label">Nama lengkap</label><input id="nama" name="nama" class="form-control" minlength="2" maxlength="100" required value="<?= e($student['nama']) ?>"></div>
            <div><label for="kelas" class="form-label">Kelas</label><input id="kelas" name="kelas" class="form-control" maxlength="20" required value="<?= e($student['kelas']) ?>" placeholder="Contoh: XII RPL 1"></div>
            <div><label for="jurusan" class="form-label">Jurusan</label><input id="jurusan" name="jurusan" class="form-control" maxlength="50" required value="<?= e($student['jurusan']) ?>"></div>
            <div><label for="jenis_kelamin" class="form-label">Jenis kelamin</label><select id="jenis_kelamin" name="jenis_kelamin" class="form-select" required><option value="L" <?= $student['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option><option value="P" <?= $student['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option></select></div>
            <div><label for="no_telepon" class="form-label">Nomor HP</label><input id="no_telepon" name="no_telepon" type="tel" class="form-control" inputmode="numeric" minlength="10" maxlength="13" pattern="[0-9]{10,13}" title="Masukkan 10–13 angka tanpa tanda baca." required value="<?= e($student['no_telepon']) ?>"></div>
            <div class="form-wide"><label for="alamat" class="form-label">Alamat</label><textarea id="alamat" name="alamat" class="form-control" rows="4" minlength="5" maxlength="2000" required><?= e($student['alamat']) ?></textarea></div>
        </div>
        <div class="form-actions"><a class="btn btn-light" href="siswa.php">Batal</a><button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Simpan siswa' ?></button></div>
    </form>
</section>
<?php render_footer(); ?>
