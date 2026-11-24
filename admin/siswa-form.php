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
$student = ['nis' => '', 'nama' => '', 'kelas_id' => '', 'jurusan_id' => '', 'alamat' => '', 'jenis_kelamin' => 'L', 'no_telepon' => '', 'foto' => null];
$errors = [];
$classes = $pdo->query('SELECT id, nama FROM kelas ORDER BY nama')->fetchAll();
$majors = $pdo->query('SELECT id, nama FROM jurusan ORDER BY nama')->fetchAll();

if ($editing) {
    $statement = $pdo->prepare('SELECT nis, nama, kelas_id, jurusan_id, alamat, jenis_kelamin, no_telepon, foto FROM siswa WHERE id = :id');
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
    foreach (['nis', 'nama', 'alamat', 'jenis_kelamin', 'no_telepon'] as $field) {
        $student[$field] = input_string($_POST, $field);
    }
    $student['nis'] = trim($student['nis']);
    $student['nama'] = normalize_text($student['nama']);
    $student['no_telepon'] = normalize_phone_number($student['no_telepon']);
    $student['alamat'] = trim($student['alamat']);
    $student['kelas_id'] = filter_var($_POST['kelas_id'] ?? null, FILTER_VALIDATE_INT);
    $student['jurusan_id'] = filter_var($_POST['jurusan_id'] ?? null, FILTER_VALIDATE_INT);

    if (!valid_student_identifier($student['nis'])) $errors[] = 'NIS wajib diisi tepat 10 angka.';
    if (!valid_person_name($student['nama'])) $errors[] = 'Nama harus terdiri dari 2–100 karakter dan hanya berisi huruf, spasi, titik, apostrof, atau tanda hubung.';
    $classIds = array_map(static fn(array $row): int => (int) $row['id'], $classes);
    $majorIds = array_map(static fn(array $row): int => (int) $row['id'], $majors);
    if (!$student['kelas_id'] || !in_array((int) $student['kelas_id'], $classIds, true)) $errors[] = 'Pilih kelas yang valid.';
    if (!$student['jurusan_id'] || !in_array((int) $student['jurusan_id'], $majorIds, true)) $errors[] = 'Pilih jurusan yang valid.';
    if (!valid_phone_number($student['no_telepon'])) $errors[] = 'Nomor HP harus berisi 10–13 angka.';
    if (!valid_postal_address($student['alamat'])) $errors[] = 'Alamat wajib diisi, panjangnya 5–2000 karakter, dan tidak boleh berisi tag HTML atau karakter kontrol.';
    if (!in_array($student['jenis_kelamin'], ['L', 'P'], true)) $errors[] = 'Pilih jenis kelamin yang valid.';

    if ($errors === []) {
        $duplicate = $pdo->prepare('SELECT id FROM siswa WHERE nis = :nis AND id <> :id LIMIT 1');
        $duplicate->execute(['nis' => $student['nis'], 'id' => $editing ? $id : 0]);
        if ($duplicate->fetch()) {
            $errors[] = 'NIS tersebut sudah digunakan siswa lain.';
        }
    }

    if ($errors === []) {
        try {
            $photoUpload = upload_student_photo($_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE], $editing ? ($found['foto'] ?? null) : null);
            if (!$photoUpload['ok']) {
                $errors[] = $photoUpload['message'];
            } else {
                $student['foto'] = $photoUpload['photo'];
            }
        } catch (Throwable $error) {
            report_database_error($error);
            $errors[] = 'Foto gagal diproses.';
        }
    }

    if ($errors === []) {
        try {
            if ($editing) {
                $oldPhoto = $found['foto'] ?? null;
                $statement = $pdo->prepare('UPDATE siswa SET nis=:nis, nama=:nama, kelas_id=:kelas_id, jurusan_id=:jurusan_id, alamat=:alamat, jenis_kelamin=:jenis_kelamin, no_telepon=:no_telepon, foto=:foto WHERE id=:id');
                $student['id'] = $id;
                $statement->execute($student);
                if ($oldPhoto !== $student['foto']) delete_student_photo($oldPhoto);
            } else {
                $statement = $pdo->prepare('INSERT INTO siswa (nis, nama, kelas_id, jurusan_id, alamat, jenis_kelamin, no_telepon, foto) VALUES (:nis, :nama, :kelas_id, :jurusan_id, :alamat, :jenis_kelamin, :no_telepon, :foto)');
                $statement->execute($student);
            }
            set_flash('success', $editing ? 'Data siswa berhasil diperbarui.' : 'Data siswa berhasil ditambahkan.');
            redirect('siswa.php');
        } catch (Throwable $error) {
            report_database_error($error);
            if (($student['foto'] ?? null) !== ($found['foto'] ?? null)) delete_student_photo($student['foto'] ?? null);
            $errors[] = 'Data gagal disimpan. Periksa kembali isian Anda.';
        }
    }
}

render_header($editing ? 'Edit Siswa' : 'Tambah Siswa', 'siswa-form.php', $user);
?>
<section class="panel form-panel">
    <div class="panel-heading"><div><h2><?= $editing ? 'Perbarui data siswa' : 'Tambah siswa baru' ?></h2><p>Lengkapi semua informasi dengan benar.</p></div><a class="btn btn-outline-primary" href="siswa.php"><i class="bi bi-arrow-left"></i> Kembali</a></div>
    <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="student-form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= e($id) ?>"><?php endif; ?>
        <div class="form-grid">
            <div><label for="nis" class="form-label">NIS</label><input id="nis" name="nis" class="form-control" inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}" title="Masukkan tepat 10 angka." required value="<?= e($student['nis']) ?>"></div>
            <div><label for="nama" class="form-label">Nama lengkap</label><input id="nama" name="nama" autocomplete="name" class="form-control" minlength="2" maxlength="100" required value="<?= e($student['nama']) ?>"></div>
            <div><label for="kelas_id" class="form-label">Kelas</label><select id="kelas_id" name="kelas_id" class="form-select" required><option value="">Pilih kelas</option><?php foreach ($classes as $class): ?><option value="<?= e($class['id']) ?>" <?= (int) $student['kelas_id'] === (int) $class['id'] ? 'selected' : '' ?>><?= e($class['nama']) ?></option><?php endforeach; ?></select></div>
            <div><label for="jurusan_id" class="form-label">Jurusan</label><select id="jurusan_id" name="jurusan_id" class="form-select" required><option value="">Pilih jurusan</option><?php foreach ($majors as $major): ?><option value="<?= e($major['id']) ?>" <?= (int) $student['jurusan_id'] === (int) $major['id'] ? 'selected' : '' ?>><?= e($major['nama']) ?></option><?php endforeach; ?></select></div>
            <div><label for="jenis_kelamin" class="form-label">Jenis kelamin</label><select id="jenis_kelamin" name="jenis_kelamin" class="form-select" required><option value="L" <?= $student['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option><option value="P" <?= $student['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option></select></div>
            <div><label for="no_telepon" class="form-label">Nomor HP</label><input id="no_telepon" name="no_telepon" type="tel" autocomplete="tel" class="form-control" inputmode="numeric" minlength="10" maxlength="13" pattern="[0-9]{10,13}" title="Masukkan 10–13 angka tanpa tanda baca." required value="<?= e($student['no_telepon']) ?>"></div>
            <div class="form-wide"><label for="alamat" class="form-label">Alamat</label><textarea id="alamat" name="alamat" class="form-control" rows="4" minlength="5" maxlength="2000" required><?= e($student['alamat']) ?></textarea></div>
            <div class="form-wide"><label for="foto" class="form-label">Foto siswa</label><input id="foto" name="foto" type="file" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png"><div class="photo-preview mt-2"><?php if (!empty($student['foto'])): ?><img src="../assets/foto/<?= e($student['foto']) ?>" alt="Foto <?= e($student['nama']) ?>" class="student-photo-preview"><?php endif; ?></div></div>
        </div>
        <div class="form-actions"><a class="btn btn-light" href="siswa.php">Batal</a><button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Simpan siswa' ?></button></div>
    </form>
</section>
<?php render_footer(); ?>
