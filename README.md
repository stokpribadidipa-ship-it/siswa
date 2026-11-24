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
4. Untuk database lama, impor `migration-relasi.sql` agar tabel `kelas`, `jurusan`, relasi, dan kolom `foto` ditambahkan tanpa menghapus data lama. Gunakan `migration-roles.sql` untuk memperbarui role pengguna lama.
5. Konfigurasi koneksi bawaan adalah host `localhost`, database `db_sekolah`, user MySQL `root`, tanpa password. Untuk konfigurasi lain, atur environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD`.
6. Buat akun admin mengikuti bagian **Membuat akun admin** di bawah.
7. Buka `http://localhost/siswa/` dan masuk menggunakan email serta password akun yang dibuat.

Jika project menggunakan database lama dengan role `admin/user`, impor `migration-roles.sql` **satu kali** sebelum membuat akun admin. Akun role `user` lama dipindahkan menjadi `siswa` dan tidak otomatis ditautkan ke NIS; admin perlu menautkannya lewat menu **Data Pengguna**. Jangan menjalankan migrasi berulang kali.

## Role pengguna

- **Admin**: menambah, mengubah, dan menghapus data siswa; mengelola akun dan role; membuat akun petugas; melihat laporan.
- **Petugas**: melihat dan mencari seluruh data siswa tanpa izin mengubah atau menghapusnya.
- **Siswa**: melihat profil siswa yang ditautkan ke akunnya sendiri.

Admin membuat akun petugas di menu **Data Pengguna**. Pendaftaran publik hanya membuat akun siswa. Sebelum siswa mendaftar, admin harus memasukkan data siswa melalui **Data Siswa → Tambah Siswa**. Siswa kemudian mendaftar memakai NIS yang cocok dengan data tersebut. Bila NIS belum ada atau sudah ditautkan ke akun lain, pendaftaran ditolak.

## Membuat akun admin

### Admin pertama melalui PowerShell

Pastikan Apache/MySQL sudah berjalan dan `database.sql` sudah diimpor. Buka PowerShell, lalu jalankan:

```powershell
C:\xampp_new\php\php.exe C:\xampp_new\htdocs\siswa\setup-admin.php "Nama Admin" "admin@sekolah.sch.id" "GANTI_DENGAN_PASSWORD_KUAT"
```

Ganti nama, email, dan teks password contoh dengan data yang ingin digunakan. Jalankan perintah dari PowerShell di komputer yang memiliki project; `setup-admin.php` memang hanya bisa digunakan melalui CLI, bukan browser. Password minimal 8 karakter dan maksimal 72 byte, lalu disimpan sebagai hash.

Setelah muncul pesan berhasil, buka `http://localhost/siswa/` dan login dengan email serta password yang tadi dipilih. Password tidak dapat dibaca kembali dari database, jadi simpan di tempat yang aman. Jangan gunakan password contoh di atas, membagikan password, atau menyimpannya di README/repository.

Jika email sudah digunakan, akun tidak dibuat ulang. Gunakan akun tersebut atau, bila sudah ada admin yang bisa login, ubah role akun lain menjadi **Admin** melalui menu **Data Pengguna**.

## Validasi data

Validasi juga dilakukan di server, sehingga tetap berlaku jika validasi browser dilewati.

- NIS: tepat 10 digit angka dan tidak boleh duplikat.
- Nomor HP: 10–13 digit angka.
- Nama, email, password, kelas, jurusan, alamat, role, dan filter diperiksa sesuai format dan batas panjangnya.
- Password minimal 8 karakter dan maksimal 72 byte.

## Fitur

- Login, pendaftaran siswa, logout, session, dan perlindungan CSRF.
- CRUD data siswa, pencarian/filter terdebounce, dan paginasi Fetch tanpa reload untuk admin/petugas; pembuatan akun petugas, perubahan role, pencarian, serta penghapusan pengguna melalui Fetch untuk admin.
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
