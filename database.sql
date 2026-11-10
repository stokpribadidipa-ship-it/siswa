CREATE DATABASE IF NOT EXISTS db_sekolah
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_sekolah;

CREATE TABLE IF NOT EXISTS siswa (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nis VARCHAR(10) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(20) NOT NULL,
    jurusan VARCHAR(50) NOT NULL,
    alamat TEXT NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    no_telepon VARCHAR(13) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_siswa_nis (nis),
    KEY idx_siswa_nama (nama),
    KEY idx_siswa_kelas (kelas),
    KEY idx_siswa_jurusan (jurusan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'petugas', 'siswa') NOT NULL DEFAULT 'siswa',
    siswa_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_siswa_id (siswa_id),
    KEY idx_users_role (role),
    CONSTRAINT fk_users_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
