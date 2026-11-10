USE db_sekolah;

-- Run once on installations created with the earlier admin/user schema.
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'user', 'petugas', 'siswa') NOT NULL DEFAULT 'siswa',
    ADD COLUMN siswa_id INT UNSIGNED NULL AFTER role;

UPDATE users
SET role = 'siswa'
WHERE role = 'user';

ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'petugas', 'siswa') NOT NULL DEFAULT 'siswa',
    ADD UNIQUE KEY uq_users_siswa_id (siswa_id),
    ADD CONSTRAINT fk_users_siswa
        FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE SET NULL;
