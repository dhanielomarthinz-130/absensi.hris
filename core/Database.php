<?php
// core/Database.php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dbDir = __DIR__ . '/../database';
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0777, true);
            }
            $dbPath = $dbDir . '/app.sqlite';
            $isNew = !file_exists($dbPath);

            try {
                self::$instance = new PDO('sqlite:' . $dbPath);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->exec('PRAGMA foreign_keys = ON;');

                if ($isNew) {
                    self::migrateAndSeed();
                } else {
                    // Cek jika tabel kosong
                    $chk = self::$instance->query("SELECT count(*) as total FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
                    if (!$chk || $chk['total'] == 0) {
                        self::migrateAndSeed();
                    }
                }
            } catch (PDOException $e) {
                die("Database Connection Error: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    public static function migrateAndSeed(): void {
        $db = self::$instance;

        // 1. Divisions Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS divisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                code TEXT NOT NULL UNIQUE,
                description TEXT,
                supervisor_id INTEGER DEFAULT NULL,
                approval_user_id INTEGER DEFAULT NULL,
                approval_target_role TEXT DEFAULT 'supervisor',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // 2. Users Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                email TEXT,
                phone TEXT,
                role TEXT NOT NULL CHECK (role IN ('superadmin', 'admin', 'supervisor', 'operator')),
                division_id INTEGER DEFAULT NULL,
                position TEXT DEFAULT 'Staff',
                avatar TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL
            );
        ");

        // 3. Shifts Table
        $db->exec("
            CREATE TABLE IF NOT EXISTS shifts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                start_time TEXT NOT NULL,
                end_time TEXT NOT NULL,
                color TEXT DEFAULT '#2563eb',
                description TEXT
            );
        ");

        // 4. Schedules Table (Roster Mingguan / Harian)
        $db->exec("
            CREATE TABLE IF NOT EXISTS schedules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                date TEXT NOT NULL,
                shift_id INTEGER NOT NULL,
                week_number INTEGER NOT NULL,
                year INTEGER NOT NULL,
                notes TEXT,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, date),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE RESTRICT
            );
        ");

        // 5. Attendances Table (Konfirmasi Presensi / Clock In - Out)
        $db->exec("
            CREATE TABLE IF NOT EXISTS attendances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                schedule_id INTEGER DEFAULT NULL,
                date TEXT NOT NULL,
                clock_in TEXT,
                clock_out TEXT,
                status TEXT CHECK (status IN ('hadir', 'terlambat', 'pulang_cepat', 'tidak_hadir')),
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, date),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");

        // 6. Leaves Table (Izin / Sakit / Cuti & Upload Surat Dokter)
        // approval_target: 'supervisor' atau 'admin' (bisa diubah atau dioverride)
        // approved_by: user_id yang menyetujui (apakah supervisor atau admin)
        $db->exec("
            CREATE TABLE IF NOT EXISTS leaves (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK (type IN ('sakit', 'izin', 'cuti')),
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                doctor_letter TEXT DEFAULT NULL,
                approval_target TEXT NOT NULL DEFAULT 'supervisor' CHECK (approval_target IN ('supervisor', 'admin')),
                status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
                approved_by INTEGER DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                reviewer_role TEXT DEFAULT NULL,
                rejection_note TEXT DEFAULT NULL,
                assigned_approver_id INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (assigned_approver_id) REFERENCES users(id) ON DELETE SET NULL
            );
        ");

        // 7. Operator Menus Settings Table (Fitur Aktifkan / Nonaktifkan / Bekukan Menu Operator)
        $db->exec("
            CREATE TABLE IF NOT EXISTS operator_menus (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                menu_key TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                subtitle TEXT,
                icon TEXT NOT NULL,
                color_class TEXT NOT NULL,
                url TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                frozen_message TEXT DEFAULT 'Menu ini sedang dibekukan oleh HR / Admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed Default Operator Menus jika belum ada
        try {
            $cntMenu = $db->query("SELECT COUNT(*) FROM operator_menus")->fetchColumn();
            if ($cntMenu == 0) {
                $menus = [
                    ['absensi_live', 'Absensi<br>Live', 'Konfirmasi Hadir (Clock)', 'ti-fingerprint', 'tile-pastel-emerald', '/operator/absensi.php', 1, 1, 'Menu Absensi Live sedang dinonaktifkan.'],
                    ['jadwal_shift', 'Jadwal<br>Shift', 'Detail Roster Kerja', 'ti-calendar-event', 'tile-pastel-blue', '/operator/jadwal.php', 1, 2, 'Menu Jadwal Shift sedang dinonaktifkan.'],
                    ['izin', 'Pengajuan<br>Izin', 'Izin Sakit & Dokter', 'ti-file-text', 'tile-pastel-rose', '/operator/izin.php', 1, 3, 'Pengajuan Izin sedang dinonaktifkan.'],
                    ['cuti', 'Cuti<br>Tahunan', 'Permohonan Cuti', 'ti-calendar-minus', 'tile-pastel-violet', '/operator/izin.php?type=cuti', 0, 4, 'Pengajuan Cuti sementara dibekukan oleh Manajemen HR.'],
                    ['riwayat', 'Riwayat<br>Presensi', 'Histori Jam Kerja', 'ti-history', 'tile-pastel-amber', '/operator/riwayat.php', 1, 5, 'Riwayat Presensi sedang dinonaktifkan.'],
                    ['karyawan', 'Daftar<br>Karyawan', 'Monitoring Rekan Tim', 'ti-users', 'tile-pastel-cyan', '/operator/karyawan.php', 1, 6, 'Direktori Karyawan sedang dinonaktifkan.']
                ];
                $stM = $db->prepare("INSERT INTO operator_menus (menu_key, title, subtitle, icon, color_class, url, is_active, sort_order, frozen_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($menus as $m) {
                    $stM->execute($m);
                }
            }
        } catch (\Throwable $e) {}

        // Migrasi Kolom Tambahan jika belum ada
        try { $db->exec("ALTER TABLE divisions ADD COLUMN approval_user_id INTEGER DEFAULT NULL"); } catch (\Throwable $e) {}
        try { $db->exec("ALTER TABLE divisions ADD COLUMN approval_target_role TEXT DEFAULT 'supervisor'"); } catch (\Throwable $e) {}
        try { $db->exec("ALTER TABLE leaves ADD COLUMN assigned_approver_id INTEGER DEFAULT NULL"); } catch (\Throwable $e) {}

        // SEEDING DATA AWAL
        self::seedData($db);
    }

    private static function seedData(PDO $db): void {
        // Cek apakah user sudah ada
        $check = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($check > 0) return;

        // 1. Seed Shifts
        $shifts = [
            ['SHIFT1', 'Shift 1', '07:00', '15:00', '#2563eb', 'Shift Pagi Operasional'],
            ['SHIFT2', 'Shift 2', '15:00', '23:00', '#8b5cf6', 'Shift Siang / Malam Operasional'],
            ['OFF', 'Libur (OFF)', '00:00', '00:00', '#64748b', 'Hari Libur / Bebas Tugas']
        ];
        $stShift = $db->prepare("INSERT INTO shifts (code, name, start_time, end_time, color, description) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($shifts as $s) {
            $stShift->execute($s);
        }

        // 2. Seed Divisions
        $divisions = [
            ['Produksi', 'PRD', 'Divisi Manufaktur & Operasional Mesin'],
            ['Teknologi Informasi', 'IT', 'Divisi IT, Sistem & Infrastruktur'],
            ['Logistik & Gudang', 'GDG', 'Divisi Pergudangan, Penerimaan & Distribusi'],
            ['Quality Control', 'QC', 'Divisi Kontrol Kualitas & Standar']
        ];
        $stDiv = $db->prepare("INSERT INTO divisions (name, code, description) VALUES (?, ?, ?)");
        foreach ($divisions as $d) {
            $stDiv->execute($d);
        }

        // 3. Seed Users
        $passSuper = password_hash('Dh@niel0', PASSWORD_DEFAULT);
        $passAdmin = password_hash('admin123', PASSWORD_DEFAULT);
        $passUser = password_hash('123456', PASSWORD_DEFAULT);

        $users = [
            // Superadmin (Akses Penuh Seluruh Sistem & Fitur Adjust)
            ['Daniel', $passSuper, 'Daniel (Super Administrator)', 'daniel@presensiku.local', '081199887766', 'superadmin', null, 'Super Administrator & Owner'],
            // Admin
            ['admin', $passAdmin, 'Super Admin HR (PresensiKu)', 'admin@presensiku.local', '081234567890', 'admin', null, 'HR & General Manager'],
            // Supervisors
            ['spv_produksi', $passUser, 'Budi Santoso, S.T.', 'budi.spv@presensiku.local', '081234567891', 'supervisor', 1, 'Supervisor Produksi'],
            ['spv_it', $passUser, 'Ahmad Fauzi, M.Kom.', 'fauzi.it@presensiku.local', '081234567892', 'supervisor', 2, 'Supervisor IT & Sysadmin'],
            // Operators Produksi
            ['operator1', $passUser, 'Rian Pratama', 'rian@presensiku.local', '081234567893', 'operator', 1, 'Operator Mesin A'],
            ['operator2', $passUser, 'Siti Aminah', 'siti@presensiku.local', '081234567894', 'operator', 1, 'Operator Packing'],
            ['operator3', $passUser, 'Dedi Kurniawan', 'dedi@presensiku.local', '081234567895', 'operator', 1, 'Operator Perakitan'],
            // Operators IT
            ['operator4', $passUser, 'Dimas Setiawan', 'dimas@presensiku.local', '081234567896', 'operator', 2, 'Helpdesk / Operator IT'],
            ['operator5', $passUser, 'Larasati Dewi', 'laras@presensiku.local', '081234567897', 'operator', 2, 'Network Support'],
            // Operators Gudang
            ['operator6', $passUser, 'Eko Prasetyo', 'eko@presensiku.local', '081234567898', 'operator', 3, 'Operator Forklift'],
            ['operator7', $passUser, 'Bambang Pamungkas', 'bambang@presensiku.local', '081234567899', 'operator', 3, 'Staff Inventory'],
            // Operators QC
            ['operator8', $passUser, 'Mega Suryani', 'mega@presensiku.local', '081234567810', 'operator', 4, 'Quality Inspector']
        ];

        $stUser = $db->prepare("INSERT INTO users (username, password_hash, full_name, email, phone, role, division_id, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($users as $u) {
            $stUser->execute($u);
        }

        // Set supervisor_id di divisions (spv_produksi id=3, spv_it id=4 karena Daniel id=1, admin id=2)
        $db->exec("UPDATE divisions SET supervisor_id = 3 WHERE id = 1"); // Budi Santoso -> Produksi
        $db->exec("UPDATE divisions SET supervisor_id = 4 WHERE id = 2"); // Ahmad Fauzi -> IT

        // 4. Seed Schedule (Untuk minggu ini & 7 hari ke depan)
        // Tanggal dinamis agar saat dijalankan, hari ini selalu memiliki jadwal
        $today = new DateTime();
        $startOfWeek = clone $today;
        $dayOfWeek = (int)$startOfWeek->format('N'); // 1 = Senin, 7 = Minggu
        $startOfWeek->modify('-' . ($dayOfWeek - 1) . ' days'); // Mundur ke hari Senin minggu ini

        $stSched = $db->prepare("INSERT INTO schedules (user_id, date, shift_id, week_number, year, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // Buat jadwal untuk 14 hari (minggu ini dan minggu depan)
        $operatorIds = [4, 5, 6, 7, 8, 9, 10, 11];
        for ($dayOffset = 0; $dayOffset < 14; $dayOffset++) {
            $curDate = clone $startOfWeek;
            $curDate->modify("+{$dayOffset} days");
            $dateStr = $curDate->format('Y-m-d');
            $weekNum = (int)$curDate->format('W');
            $yearNum = (int)$curDate->format('Y');
            $dayIdx = (int)$curDate->format('N'); // 1 = Senin, 7 = Minggu

            foreach ($operatorIds as $idx => $uid) {
                // Pola pembagian shift: sebagian Shift 1 (id:1), sebagian Shift 2 (id:2), hari Minggu libur (id:3)
                if ($dayIdx === 7) {
                    $shiftId = 3; // Off pada hari Minggu
                } else {
                    // Bergantian shift
                    if ($uid % 2 === 0) {
                        $shiftId = ($dayOffset < 7) ? 1 : 2;
                    } else {
                        $shiftId = ($dayOffset < 7) ? 2 : 1;
                    }
                }
                $stSched->execute([$uid, $dateStr, $shiftId, $weekNum, $yearNum, 'Jadwal Roster Reguler', 1]);
            }
        }

        // 5. Seed Attendance Hari Ini untuk beberapa operator (sebagai contoh monitoring)
        $todayStr = $today->format('Y-m-d');
        $stAtt = $db->prepare("INSERT INTO attendances (user_id, date, clock_in, clock_out, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
        
        $rianId = $db->query("SELECT id FROM users WHERE username = 'operator1'")->fetchColumn() ?: 5;
        $dimasId = $db->query("SELECT id FROM users WHERE username = 'operator4'")->fetchColumn() ?: 8;
        $ekoId = $db->query("SELECT id FROM users WHERE username = 'operator6'")->fetchColumn() ?: 10;
        $sitiId = $db->query("SELECT id FROM users WHERE username = 'operator2'")->fetchColumn() ?: 6;

        // Rian Pratama (Shift 1, Hadir tepat waktu 06:55)
        $stAtt->execute([$rianId, $todayStr, '06:55:12', null, 'hadir', 'Check-in tepat waktu']);
        // Dimas Setiawan (IT, Shift 2 / Shift 1, Hadir 07:15 - terlambat)
        $stAtt->execute([$dimasId, $todayStr, '07:15:30', null, 'terlambat', 'Terlambat 15 menit']);
        // Eko Prasetyo (Gudang, Hadir 06:48)
        $stAtt->execute([$ekoId, $todayStr, '06:48:02', null, 'hadir', 'Check-in Shift Pagi']);

        // 6. Buat contoh surat dokter dummy
        $dummySuratFile = __DIR__ . '/../uploads/surat_dokter/sample_surat_dokter.svg';
        $svgContent = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">
  <rect width="100%" height="100%" fill="#ffffff" stroke="#cbd5e1" stroke-width="4"/>
  <rect x="20" y="20" width="560" height="70" fill="#f8fafc" rx="8"/>
  <text x="50" y="50" font-family="Arial, sans-serif" font-size="20" font-weight="bold" fill="#0f172a">KLINIK MEDIKA SEHAT UTAMA</text>
  <text x="50" y="72" font-family="Arial, sans-serif" font-size="12" fill="#64748b">SIP Dokter: 445/092/SIP-D/2026 | Jl. Kesehatan No. 128, Jakarta</text>
  <line x1="20" y1="105" x2="580" y2="105" stroke="#0284c7" stroke-width="2"/>
  <text x="210" y="135" font-family="Arial, sans-serif" font-size="16" font-weight="bold" fill="#0369a1">SURAT KETERANGAN DOKTER</text>
  <text x="50" y="175" font-family="Arial, sans-serif" font-size="13" fill="#334155">Menerangkan bahwa pasien:</text>
  <text x="70" y="205" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#0f172a">Nama: Siti Aminah (Operator 2)</text>
  <text x="70" y="225" font-family="Arial, sans-serif" font-size="13" fill="#334155">Diagnosa: ISPA / Demam Akut</text>
  <text x="50" y="260" font-family="Arial, sans-serif" font-size="13" fill="#334155">Perlu beristirahat selama 2 (dua) hari terhitung sejak tanggal pemeriksaan.</text>
  <text x="400" y="320" font-family="Arial, sans-serif" font-size="12" fill="#334155">Jakarta, {$todayStr}</text>
  <text x="400" y="365" font-family="Arial, sans-serif" font-size="13" font-weight="bold" fill="#0f172a">dr. Hendra Wijaya, Sp.PD</text>
</svg>
SVG;
        @file_put_contents($dummySuratFile, $svgContent);

        // 7. Seed Leaves (Siti Aminah id=$sitiId mengajukan sakit dengan surat dokter, approval_target = 'supervisor')
        $stLeave = $db->prepare("INSERT INTO leaves (user_id, type, start_date, end_date, reason, doctor_letter, approval_target, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stLeave->execute([
            $sitiId, // Siti Aminah (operator2)
            'sakit',
            $todayStr,
            $today->modify('+1 day')->format('Y-m-d'),
            'Demam tinggi dan radang tenggorokan akut sesuai anjuran dokter',
            'sample_surat_dokter.svg',
            'supervisor',
            'pending'
        ]);
    }
}
