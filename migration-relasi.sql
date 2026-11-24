USE db_sekolah;

CREATE TABLE IF NOT EXISTS kelas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kelas_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jurusan (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jurusan_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();
SET @has_kelas_id := (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=@db AND table_name='siswa' AND column_name='kelas_id');
SET @has_jurusan_id := (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=@db AND table_name='siswa' AND column_name='jurusan_id');
SET @has_foto := (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=@db AND table_name='siswa' AND column_name='foto');

SET @sql := IF(@has_kelas_id=0,
    'ALTER TABLE siswa ADD COLUMN kelas_id INT UNSIGNED NULL AFTER nama',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_jurusan_id=0,
    'ALTER TABLE siswa ADD COLUMN jurusan_id INT UNSIGNED NULL AFTER kelas_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_foto=0,
    'ALTER TABLE siswa ADD COLUMN foto VARCHAR(255) NULL AFTER no_telepon',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO kelas (nama)
SELECT DISTINCT TRIM(kelas) FROM siswa
WHERE kelas IS NOT NULL AND TRIM(kelas) <> '';

INSERT IGNORE INTO jurusan (nama)
SELECT DISTINCT TRIM(jurusan) FROM siswa
WHERE jurusan IS NOT NULL AND TRIM(jurusan) <> '';

UPDATE siswa s
JOIN kelas k ON k.nama = TRIM(s.kelas)
SET s.kelas_id = k.id
WHERE s.kelas_id IS NULL;

UPDATE siswa s
JOIN jurusan j ON j.nama = TRIM(s.jurusan)
SET s.jurusan_id = j.id
WHERE s.jurusan_id IS NULL;

SET @missing_class := (SELECT COUNT(*) FROM siswa WHERE kelas_id IS NULL);
SET @missing_major := (SELECT COUNT(*) FROM siswa WHERE jurusan_id IS NULL);
SELECT IF(@missing_class=0 AND @missing_major=0,
    'Migrasi relasi berhasil',
    'Ada data lama yang belum memiliki kelas atau jurusan') AS status_migrasi;

ALTER TABLE siswa
    MODIFY COLUMN kelas_id INT UNSIGNED NOT NULL,
    MODIFY COLUMN jurusan_id INT UNSIGNED NOT NULL;

SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE constraint_schema=@db AND table_name='siswa' AND constraint_name='fk_siswa_kelas');
SET @sql := IF(@fk=0,
    'ALTER TABLE siswa ADD CONSTRAINT fk_siswa_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE constraint_schema=@db AND table_name='siswa' AND constraint_name='fk_siswa_jurusan');
SET @sql := IF(@fk=0,
    'ALTER TABLE siswa ADD CONSTRAINT fk_siswa_jurusan FOREIGN KEY (jurusan_id) REFERENCES jurusan(id) ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
