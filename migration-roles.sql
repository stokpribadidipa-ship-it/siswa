USE db_sekolah;

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'user', 'petugas', 'siswa') NOT NULL DEFAULT 'siswa';

ALTER TABLE users ADD COLUMN IF NOT EXISTS siswa_id INT UNSIGNED NULL AFTER role;

UPDATE users SET role = 'siswa' WHERE role = 'user';

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'petugas', 'siswa') NOT NULL DEFAULT 'siswa';
