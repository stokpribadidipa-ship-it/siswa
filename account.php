<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';
require_auth(['admin', 'petugas', 'siswa']);
redirect('admin/pengaturan.php');
