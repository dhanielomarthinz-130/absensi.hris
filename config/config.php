<?php
// config/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

// Nama Aplikasi
define('APP_NAME', 'HRIS IEG');
define('APP_TAGLINE', 'Sistem Manajemen Roster Shift & Presensi');

// URL Base Path (kompatibel ganda: Apache XAMPP subfolder /jadwal.absensi atau PHP built-in server root)
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (strpos($scriptName, '/jadwal.absensi') === 0) {
    define('BASE_URL', '/jadwal.absensi');
} else {
    define('BASE_URL', '');
}

// Upload Folder
define('UPLOAD_DIR', __DIR__ . '/../uploads/surat_dokter/');
define('UPLOAD_URL', BASE_URL . '/uploads/surat_dokter/');

// Pastikan direktori upload tersedia
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0777, true);
}
