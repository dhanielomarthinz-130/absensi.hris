<?php
// core/Helpers.php
require_once __DIR__ . '/../config/config.php';

class Helpers {
    public static function formatTanggalIndo(?string $dateStr, bool $withDay = true): string {
        if (!$dateStr) return '-';
        $timestamp = strtotime($dateStr);
        if (!$timestamp) return $dateStr;

        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        $dayIndex = (int)date('w', $timestamp);
        $dayNum = date('d', $timestamp);
        $monthNum = (int)date('m', $timestamp);
        $year = date('Y', $timestamp);

        $result = "{$dayNum} {$bulan[$monthNum]} {$year}";
        if ($withDay) {
            $result = "{$hari[$dayIndex]}, " . $result;
        }
        return $result;
    }

    public static function formatJam(?string $timeStr): string {
        if (!$timeStr) return '-';
        return date('H:i', strtotime($timeStr)) . ' WIB';
    }

    public static function setFlash(string $type, string $message): void {
        $_SESSION['flash'] = [
            'type' => $type, // success, error, warning, info
            'message' => $message
        ];
    }

    public static function getFlash(): ?array {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    public static function handleUploadSuratDokter(array $file): array {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Parameter berkas tidak valid.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Gagal mengunggah berkas. Kode error: ' . $file['error']];
        }

        // Batas ukuran 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Ukuran berkas melebihi batas maksimal (5MB).'];
        }

        // Validasi tipe berkas
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'image/svg+xml' => 'svg'
        ];

        if (!array_key_exists($mime, $allowedMimes)) {
            return ['success' => false, 'error' => 'Format berkas tidak diizinkan. Gunakan JPG, PNG, WEBP, atau PDF.'];
        }

        $extension = $allowedMimes[$mime];
        $filename = 'surat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $destination = UPLOAD_DIR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Gagal menyimpan berkas ke server.'];
        }

        return ['success' => true, 'filename' => $filename];
    }

    public static function shiftBadge(string $code, string $name, string $color = '#2563eb'): string {
        $cleanName = htmlspecialchars($name);
        $cleanColor = htmlspecialchars($color);
        return "<span class=\"badge-shift\" style=\"background-color: {$cleanColor}15; color: {$cleanColor}; border: 1px solid {$cleanColor}40;\">{$cleanName}</span>";
    }

    public static function statusBadge(string $status): string {
        switch (strtolower($status)) {
            case 'hadir':
                return '<span class="status-badge status-hadir"><i class="ti ti-check"></i> Hadir</span>';
            case 'terlambat':
                return '<span class="status-badge status-terlambat"><i class="ti ti-clock"></i> Terlambat</span>';
            case 'pulang_cepat':
                return '<span class="status-badge status-pulang"><i class="ti ti-arrow-left"></i> Pulang Cepat</span>';
            case 'sakit':
                return '<span class="status-badge status-sakit"><i class="ti ti-first-aid-kit"></i> Sakit</span>';
            case 'izin':
                return '<span class="status-badge status-izin"><i class="ti ti-calendar-event"></i> Izin</span>';
            case 'cuti':
                return '<span class="status-badge status-cuti"><i class="ti ti-beach"></i> Cuti</span>';
            case 'pending':
                return '<span class="status-badge status-pending"><i class="ti ti-hourglass"></i> Menunggu Approval</span>';
            case 'approved':
                return '<span class="status-badge status-hadir"><i class="ti ti-circle-check"></i> Disetujui</span>';
            case 'rejected':
                return '<span class="status-badge status-alpha"><i class="ti ti-circle-x"></i> Ditolak</span>';
            case 'tidak_hadir':
            default:
                return '<span class="status-badge status-alpha"><i class="ti ti-alert-triangle"></i> Alpha / Belum Hadir</span>';
        }
    }

    public static function getOperatorMenus(): array {
        $db = Database::getConnection();
        try {
            $stmt = $db->query("SELECT * FROM operator_menus ORDER BY sort_order ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function isOperatorMenuFrozen(string $key): bool {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("SELECT is_active FROM operator_menus WHERE menu_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val !== false && (int)$val === 0);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

