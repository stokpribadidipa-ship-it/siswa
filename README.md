# DataSiswa

Aplikasi pengelolaan data siswa berbasis PHP Native, PDO, dan MySQL/MariaDB. Aplikasi menggunakan session PHP dan menyimpan data akun serta siswa di database.

## Persyaratan

- XAMPP dengan PHP 8.1 atau lebih baru
- Ekstensi PHP `pdo_mysql` dan `mbstring`
- MySQL/MariaDB aktif
- Koneksi internet untuk Bootstrap dan Bootstrap Icons dari CDN

## Instalasi di XAMPP

1. Letakkan project di `C:\xampp_new\htdocs\siswa` atau document root Apache Anda.
2. Jalankan Apache dan MySQL melalui XAMPP Control Panel.
3. Buka phpMyAdmin (`http://localhost/phpmyadmin`) dan impor `database.sql`. File ini membuat database `db_sekolah` dan tabel `siswa` serta `users`.
4. Konfigurasi koneksi bawaan adalah host `localhost`, database `db_sekolah`, user MySQL `root`, tanpa password. Untuk konfigurasi lain, atur environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD`.
5. Buat akun admin pertama dari PowerShell:

   ```powershell
   C:\xampp_new\php\php.exe C:\xampp_new\htdocs\siswa\setup-admin.php "admin" admin@sekolah.sch.id "GANTI-DENGAN-PASSWORD-KUAT"
   ```

   Ganti password contoh dengan password rahasia milik Anda. Perintah hanya dapat dijalankan lewat CLI dan password disimpan menggunakan `password_hash()`. Nama tampilan akun adalah `admin`; untuk login, gunakan email `admin@sekolah.sch.id` dan password yang dibuat.
   
   Jika email tersebut sudah terdaftar, jangan jalankan perintah pembuatan lagi. Gunakan akun admin yang sudah ada atau ubah nama/email melalui **Pengaturan Akun**.
6. Buka `http://localhost/siswa/` dan masuk sebagai admin.

Jika project menggunakan database lama dengan role `admin/user`, impor `migration-roles.sql` **satu kali** sebelum membuat akun admin. Akun role `user` lama dipindahkan menjadi `siswa` dan tidak otomatis ditautkan ke NIS; admin perlu menautkannya lewat menu **Data Pengguna**. Jangan menjalankan migrasi berulang kali.

## Role pengguna

- **Admin**: menambah, mengubah, dan menghapus data siswa; mengelola akun dan role; membuat akun petugas; melihat laporan.
- **Petugas**: melihat dan mencari seluruh data siswa tanpa izin mengubah atau menghapusnya.
- **Siswa**: melihat profil siswa yang ditautkan ke akunnya sendiri.

Admin membuat akun petugas di menu **Data Pengguna**. Pendaftaran publik hanya membuat akun siswa. Sebelum siswa mendaftar, admin harus memasukkan data siswa melalui **Data Siswa → Tambah Siswa**. Siswa kemudian mendaftar memakai NIS yang cocok dengan data tersebut. Bila NIS belum ada atau sudah ditautkan ke akun lain, pendaftaran ditolak.

## Validasi data

Validasi juga dilakukan di server, sehingga tetap berlaku jika validasi browser dilewati.

- NIS: tepat 10 digit angka dan tidak boleh duplikat.
- Nomor HP: 10–13 digit angka.
- Nama, email, password, kelas, jurusan, alamat, role, dan filter diperiksa sesuai format dan batas panjangnya.
- Password minimal 8 karakter dan maksimal 72 byte.

## Fitur

- Login, pendaftaran siswa, logout, session, dan perlindungan CSRF.
- CRUD data siswa untuk admin, pencarian/filter untuk admin dan petugas, serta profil terbatas untuk siswa.
- Pengelolaan pengguna, pengaturan akun, laporan, dan cetak laporan.
- Konfirmasi sebelum penghapusan dan dialog pesan kesalahan input.

## Diagnostik

Periksa sintaks semua file PHP melalui PowerShell:

```powershell
Get-ChildItem C:\xampp_new\htdocs\siswa -Recurse -Filter *.php | ForEach-Object { C:\xampp_new\php\php.exe -l $_.FullName }
```

Periksa sintaks JavaScript:

```powershell
node --check C:\xampp_new\htdocs\siswa\assets\js\site.js
```

Jika database atau kolom role tidak sesuai, buka `database-setup.php` untuk petunjuk. Jangan mengimpor ulang file SQL pada database produksi sebelum membuat backup.

## Keamanan

- Jangan memakai password contoh dari dokumentasi.
- Jangan menyimpan password atau kredensial database di file publik atau repository.
- Atur kredensial database melalui environment variable pada deployment.
- Buat backup database secara berkala.
