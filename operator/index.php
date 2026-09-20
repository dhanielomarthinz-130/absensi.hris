<?php
// operator/index.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['operator', 'supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();
$userId = $currentUser['id'];
$divId = (int)($currentUser['division_id'] ?: 1);
$todayStr = date('Y-m-d');
$activePage = 'op_dashboard';

// PROSES POST: Clock In & Clock Out Langsung Dari Dashboard
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    $currentTime = date('H:i:s');
    $notes = trim($_POST['notes'] ?? '');

    $stSh = $db->prepare("SELECT s.*, sh.start_time, sh.id as shift_id FROM schedules s JOIN shifts sh ON s.shift_id = sh.id WHERE s.user_id = ? AND s.date = ?");
    $stSh->execute([$userId, $todayStr]);
    $curSched = $stSh->fetch();

    if ($_POST['action'] === 'quick_clock_in') {
        $status = 'hadir';
        if ($curSched && $curSched['shift_id'] != 3) {
            $shiftStart = $curSched['start_time'] . ':00';
            $threshold = date('H:i:s', strtotime($shiftStart . ' +15 minutes'));
            if ($currentTime > $threshold) {
                $status = 'terlambat';
            }
        }

        $stmtIn = $db->prepare("
            INSERT INTO attendances (user_id, schedule_id, date, clock_in, status, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT(user_id, date) DO UPDATE SET clock_in = excluded.clock_in, status = excluded.status, notes = excluded.notes
        ");
        $stmtIn->execute([
            $userId,
            $curSched['id'] ?? null,
            $todayStr,
            $currentTime,
            $status,
            $notes ?: ($status === 'terlambat' ? 'Terlambat check-in' : 'Hadir tepat waktu')
        ]);

        Helpers::setFlash('success', "Clock-In berhasil dicatat pukul {$currentTime} WIB (" . strtoupper($status) . ").");
        header('Location: ' . BASE_URL . '/operator/index.php');
        exit;
    }

    if ($_POST['action'] === 'quick_clock_out') {
        $stmtOut = $db->prepare("
            UPDATE attendances 
            SET clock_out = ?, notes = CASE WHEN notes IS NULL OR notes = '' THEN ? ELSE notes || ' | ' || ? END
            WHERE user_id = ? AND date = ?
        ");
        $outNote = "Clock-out pada " . $currentTime;
        $stmtOut->execute([$currentTime, $outNote, $outNote, $userId, $todayStr]);

        Helpers::setFlash('success', "Clock-Out berhasil dicatat pukul {$currentTime} WIB.");
        header('Location: ' . BASE_URL . '/operator/index.php');
        exit;
    }
}

// Ambil Jadwal Hari Ini
$sqlTodaySched = "
    SELECT s.*, sh.name as shift_name, sh.start_time, sh.end_time, sh.color as shift_color
    FROM schedules s
    JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.user_id = ? AND s.date = ?
";
$stmtToday = $db->prepare($sqlTodaySched);
$stmtToday->execute([$userId, $todayStr]);
$todaySchedule = $stmtToday->fetch();

// Ambil Status Absensi Hari Ini
$stmtAtt = $db->prepare("SELECT * FROM attendances WHERE user_id = ? AND date = ?");
$stmtAtt->execute([$userId, $todayStr]);
$todayAttendance = $stmtAtt->fetch();

// Ambil Status Izin Hari Ini (Jika ada)
$stmtLeave = $db->prepare("SELECT * FROM leaves WHERE user_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'");
$stmtLeave->execute([$userId, $todayStr]);
$activeLeave = $stmtLeave->fetch();

// Ambil Jadwal Kerja Minggu Ini (Senin s/d Minggu)
$currentYear = (int)date('Y');
$currentWeek = (int)date('W');
$dto = new DateTime();
$dto->setISODate($currentYear, $currentWeek, 1);
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $cur = clone $dto;
    $cur->modify("+{$i} days");
    $dateKey = $cur->format('Y-m-d');
    $weekDates[$dateKey] = [
        'date' => $dateKey,
        'day_name' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][$i],
        'day_num' => $cur->format('d M'),
        'is_today' => ($dateKey === $todayStr)
    ];
}
$firstDay = array_key_first($weekDates);
$lastDay = array_key_last($weekDates);

$sqlWeekSched = "
    SELECT s.date, s.shift_id, sh.name as shift_name, sh.start_time, sh.end_time, sh.color as shift_color,
           a.clock_in, a.clock_out, a.status as attendance_status,
           l.type as leave_type, l.status as leave_status
    FROM schedules s
    JOIN shifts sh ON s.shift_id = sh.id
    LEFT JOIN attendances a ON a.user_id = s.user_id AND a.date = s.date
    LEFT JOIN leaves l ON l.user_id = s.user_id AND l.status = 'approved' AND s.date BETWEEN l.start_date AND l.end_date
    WHERE s.user_id = ? AND s.date BETWEEN ? AND ?
    ORDER BY s.date ASC
";
$stmtWeek = $db->prepare($sqlWeekSched);
$stmtWeek->execute([$userId, $firstDay, $lastDay]);
$weekSchedules = [];
while ($row = $stmtWeek->fetch()) {
    $weekSchedules[$row['date']] = $row;
}

// Rekan Satu Divisi Hari Ini
$sqlTeammates = "
    SELECT u.full_name, u.position, sh.name as shift_name, a.clock_in, a.status as att_status
    FROM users u
    JOIN schedules s ON s.user_id = u.id AND s.date = ?
    JOIN shifts sh ON s.shift_id = sh.id
    LEFT JOIN attendances a ON a.user_id = u.id AND a.date = ?
    WHERE u.division_id = ? AND u.role = 'operator' AND u.id != ?
    ORDER BY u.full_name ASC
    LIMIT 6
";
$stmtTeammates = $db->prepare($sqlTeammates);
$stmtTeammates->execute([$todayStr, $todayStr, $divId, $userId]);
$teammates = $stmtTeammates->fetchAll();

// Ambil Menu Layanan Mandiri Operator dari Pengaturan Sistem
$operatorMenus = Helpers::getOperatorMenus();

// Cek hak akses Supervisor/Admin & hitung pending approval izin tim
$userRole = $currentUser['role'] ?? 'operator';
$isSupervisorOrAdmin = in_array($userRole, ['supervisor', 'admin']);
$spvPendingCount = 0;
if ($isSupervisorOrAdmin) {
    try {
        if ($userRole === 'admin' || Auth::isSuperAdmin()) {
            $spvPendingCount = (int)$db->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
        } else {
            $stSpvPend = $db->prepare("SELECT COUNT(*) FROM leaves l JOIN users u ON l.user_id = u.id WHERE u.division_id = ? AND l.status = 'pending'");
            $stSpvPend->execute([$divId]);
            $spvPendingCount = (int)$stSpvPend->fetchColumn();
        }
    } catch (\Throwable $e) {}
}

$hour = (int)date('H');
if ($hour < 11) {
    $greeting = 'Selamat Pagi';
} elseif ($hour < 15) {
    $greeting = 'Selamat Siang';
} elseif ($hour < 18) {
    $greeting = 'Selamat Sore';
} else {
    $greeting = 'Selamat Malam';
}

$pageTitle = 'Dashboard Karyawan';
$pageSubtitle = 'Portal Presensi Mandiri & Penjadwalan Kerja';

require_once __DIR__ . '/../templates/header.php';
?>

<div style="max-width: 540px; margin: 0 auto; padding-bottom: 24px;">

    <!-- 1. TOP GREETING / PROFILE ROW (MATCHING TALENTA) -->
    <div class="talenta-app-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="<?= BASE_URL ?>/operator/profile.php" style="text-decoration: none; flex-shrink: 0;" title="Kelola Profil & Foto">
                <div style="width: 44px; height: 44px; border-radius: 50%; overflow: hidden; border: 2px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                    <?php if (!empty($currentUser['avatar']) && file_exists(__DIR__ . '/../uploads/avatars/' . $currentUser['avatar'])): ?>
                        <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($currentUser['avatar']) ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #4f46e5, #4338ca); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem;">
                            <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </a>
            <div>
                <div style="font-size: 0.775rem; color: #64748b; font-weight: 500; line-height: 1.2;">
                    <?= $greeting ?>,
                </div>
                <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; line-height: 1.25; margin-top: 2px;">
                    <?= htmlspecialchars($currentUser['full_name']) ?>
                </div>
            </div>
        </div>

        <!-- Right: Gift Icon & Settings -->
        <div style="display: flex; align-items: center; gap: 4px;">
            <button type="button" onclick="openSettingsDrawer('main')" style="background: none; border: none; padding: 6px; cursor: pointer; color: #dc2626; display: flex; align-items: center; justify-content: center;" title="Hadiah & Notifikasi">
                <i class="ti ti-gift" style="font-size: 1.5rem;"></i>
            </button>
        </div>
    </div>

    <!-- 2. THE SIGNATURE RED-TOPPED SHIFT & ATTENDANCE CARD -->
    <div class="talenta-shift-card-red">
        <!-- Top Bar Merah -->
        <div class="talenta-shift-card-topbar">
            Jadwal shift untuk <?= Helpers::formatTanggalIndo($todayStr) ?>
        </div>

        <!-- Body Card -->
        <div class="talenta-shift-card-body">
            <!-- Nama Shift -->
            <div class="talenta-shift-name-title">
                <?= $todaySchedule && $todaySchedule['shift_id'] != 3 ? htmlspecialchars($todaySchedule['shift_name']) : 'Libur (OFF)' ?>
            </div>

            <!-- Jam Shift -->
            <div class="talenta-shift-time-sub">
                <?php if ($todaySchedule && $todaySchedule['shift_id'] != 3): ?>
                    <?= htmlspecialchars($todaySchedule['start_time']) ?> - <?= htmlspecialchars($todaySchedule['end_time']) ?>
                <?php else: ?>
                    Tidak Ada Jadwal Kerja Hari Ini
                <?php endif; ?>
            </div>

            <!-- Dual Clock Box (White Card: Clock In | Clock Out) -->
            <div class="talenta-dual-clock-box">
                <!-- Clock In Section -->
                <div style="flex: 1; display: flex; align-items: center; justify-content: center;">
                    <?php if (empty($todayAttendance['clock_in'])): ?>
                        <form method="POST" action="" style="margin: 0; width: 100%;">
                            <input type="hidden" name="action" value="quick_clock_in">
                            <button type="submit" class="talenta-dual-clock-btn">
                                <i class="ti ti-login" style="font-size: 1.4rem; color: #dc2626;"></i>
                                <span style="font-weight: 800; font-size: 0.95rem; color: #0f172a;">Clock In</span>
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px;">
                            <i class="ti ti-circle-check-filled" style="font-size: 1.35rem; color: #10b981;"></i>
                            <div style="text-align: left;">
                                <span style="font-weight: 800; font-size: 0.9rem; color: #10b981; display: block; line-height: 1.1;">Clock In</span>
                                <span style="font-size: 0.72rem; color: #64748b; font-family: monospace; font-weight: 700;"><?= substr($todayAttendance['clock_in'], 0, 5) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Divider Line -->
                <div class="talenta-dual-clock-divider"></div>

                <!-- Clock Out Section -->
                <div style="flex: 1; display: flex; align-items: center; justify-content: center;">
                    <?php if (!empty($todayAttendance['clock_out'])): ?>
                        <div style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px;">
                            <i class="ti ti-circle-check-filled" style="font-size: 1.35rem; color: #ea580c;"></i>
                            <div style="text-align: left;">
                                <span style="font-weight: 800; font-size: 0.9rem; color: #ea580c; display: block; line-height: 1.1;">Clock Out</span>
                                <span style="font-size: 0.72rem; color: #64748b; font-family: monospace; font-weight: 700;"><?= substr($todayAttendance['clock_out'], 0, 5) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" style="margin: 0; width: 100%;">
                            <input type="hidden" name="action" value="quick_clock_out">
                            <button type="submit" class="talenta-dual-clock-btn">
                                <i class="ti ti-logout" style="font-size: 1.4rem; color: #ea580c;"></i>
                                <span style="font-weight: 800; font-size: 0.95rem; color: #0f172a;">Clock Out</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Feedback Subtext -->
            <div style="font-size: 0.775rem; color: #64748b; margin-top: 6px;">
                <?php if (!empty($todayAttendance['clock_out'])): ?>
                    Anda telah menyelesaikan shift kerja hari ini (Clock out: <?= substr($todayAttendance['clock_out'], 0, 5) ?> WIB)
                <?php elseif (!empty($todayAttendance['clock_in'])): ?>
                    Anda telah berhasil clock in pada pukul <?= substr($todayAttendance['clock_in'], 0, 5) ?> WIB
                <?php else: ?>
                    Anda belum melakukan clock in hari ini
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 3. SEMUA MENU APLIKASI (2 BARIS X 4 KOLOM - SEMUA MENU DITAMPILKAN LANGSUNG) -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px 8px; margin-bottom: 24px; text-align: center;">
        <!-- 1. Presensi Online -->
        <a href="<?= BASE_URL ?>/operator/absensi.php" class="talenta-app-item">
            <div class="talenta-app-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <i class="ti ti-map-pin"></i>
            </div>
            <span class="talenta-app-label">Presensi Online</span>
        </a>

        <!-- 2. Daftar Kehadiran -->
        <a href="<?= BASE_URL ?>/operator/riwayat.php" class="talenta-app-item">
            <div class="talenta-app-icon" style="background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);">
                <i class="ti ti-calendar-stats"></i>
            </div>
            <span class="talenta-app-label">Daftar Kehadiran</span>
        </a>

        <!-- 3. Kalender Shift -->
        <a href="<?= BASE_URL ?>/operator/jadwal.php" class="talenta-app-item">
            <div class="talenta-app-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                <i class="ti ti-calendar"></i>
            </div>
            <span class="talenta-app-label">Kalender Shift</span>
        </a>

        <!-- 4. Pengajuan Izin -->
        <a href="<?= BASE_URL ?>/operator/izin.php" class="talenta-app-item">
            <div class="talenta-app-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="ti ti-file-text"></i>
            </div>
            <span class="talenta-app-label">Pengajuan Izin</span>
        </a>

        <?php if (in_array($currentUser['role'], ['supervisor', 'admin', 'superadmin'])): ?>
        <!-- 5. Approval (Hanya Supervisor/Admin) -->
        <a href="<?= BASE_URL ?>/supervisor/izin.php" class="talenta-app-item">
            <div class="talenta-app-icon" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
                <i class="ti ti-clipboard-check"></i>
            </div>
            <span class="talenta-app-label">Approval</span>
        </a>
        <?php endif; ?>

        <!-- 6. Karyawan -->
        <a href="<?= BASE_URL ?>/operator/karyawan.php" class="talenta-app-item">
            <div class="talenta-app-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                <i class="ti ti-users"></i>
            </div>
            <span class="talenta-app-label">Karyawan</span>
        </a>

        <!-- 7. Inbox / Pesan -->
        <a href="<?= BASE_URL ?>/operator/inbox.php" class="talenta-app-item">
            <div class="talenta-app-icon" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                <i class="ti ti-bell"></i>
            </div>
            <span class="talenta-app-label">Kotak Masuk</span>
        </a>

        <!-- 8. Akun Profil -->
        <a href="<?= BASE_URL ?>/operator/profile.php" class="talenta-app-item">
            <div class="talenta-app-icon" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);">
                <i class="ti ti-user"></i>
            </div>
            <span class="talenta-app-label">Akun Saya</span>
        </a>
    </div>

    <!-- 6. JADWAL SHIFT MINGGU INI (ROSTER STRIP 7 HARI) -->
    <div id="jadwalSection" style="margin-bottom: 18px;">
        <div style="background: #ffffff; border-radius: 18px; padding: 14px 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 10px; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 1.05rem;">
                        <i class="ti ti-calendar-week"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; font-weight: 800; color: #0f172a; line-height: 1.2;">Kalender Shift Minggu Ini</div>
                        <div style="font-size: 0.68rem; color: #64748b;">Roster Kerja Week <?= $currentWeek ?>, <?= $currentYear ?></div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/operator/jadwal.php" style="font-size: 0.75rem; font-weight: 700; color: #2563eb; text-decoration: none;">
                    Selengkapnya <i class="ti ti-chevron-right"></i>
                </a>
            </div>

            <!-- 7-Day Roster Cards -->
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px;">
                <?php foreach ($weekDates as $dt => $w): 
                    $sc = $weekSchedules[$dt] ?? null;
                    $isShift1 = $sc && $sc['shift_id'] == 1;
                    $isShift2 = $sc && $sc['shift_id'] == 2;
                    $isOff = $sc && $sc['shift_id'] == 3;
                    $isToday = $w['is_today'];
                ?>
                    <a href="<?= BASE_URL ?>/operator/jadwal.php" style="text-decoration: none; display: block; padding: 7px 2px; text-align: center; border-radius: 10px; border: <?= $isToday ? '2px solid #4338ca' : ($sc ? '1px solid #e2e8f0' : '1px solid #f1f5f9') ?>; background: <?= $isToday ? '#eef2ff' : ($isOff ? '#f8fafc' : '#ffffff') ?>; position: relative;">
                        <?php if ($isToday): ?>
                            <div style="font-size: 0.45rem; font-weight: 800; background: #4338ca; color: #ffffff; padding: 1px 3px; border-radius: 3px; position: absolute; top: -5px; left: 50%; transform: translateX(-50%); letter-spacing: 0.2px; white-space: nowrap;">
                                HARI INI
                            </div>
                        <?php endif; ?>

                        <div style="font-size: 0.6rem; font-weight: 700; color: <?= $isToday ? '#4338ca' : '#64748b' ?>; text-transform: uppercase;">
                            <?= substr($w['day_name'], 0, 3) ?>
                        </div>
                        <div style="font-size: 0.85rem; font-weight: 800; color: <?= $isToday ? '#1e1b4b' : '#0f172a' ?>; margin: 1px 0 2px;">
                            <?= date('d', strtotime($dt)) ?>
                        </div>

                        <?php if ($sc): ?>
                            <div>
                                <?php if ($isShift1): ?>
                                    <span style="display: block; font-size: 0.575rem; font-weight: 800; background: #dbeafe; color: #1e40af; border-radius: 4px; padding: 1px 0;">S1</span>
                                <?php elseif ($isShift2): ?>
                                    <span style="display: block; font-size: 0.575rem; font-weight: 800; background: #f3e8ff; color: #6b21a8; border-radius: 4px; padding: 1px 0;">S2</span>
                                <?php elseif ($isOff): ?>
                                    <span style="display: block; font-size: 0.55rem; font-weight: 700; background: #f1f5f9; color: #64748b; border-radius: 4px; padding: 1px 0;">OFF</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 0.6rem; color: #cbd5e1;">-</div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Informasi Menu Dibekukan -->
<div id="frozenNoticeModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #ffffff; border-radius: 22px; max-width: 420px; width: 100%; padding: 24px; text-align: center; box-shadow: 0 16px 36px rgba(0,0,0,0.2);">
        <div style="width: 56px; height: 56px; border-radius: 18px; background: #fee2e2; color: #e11d48; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 14px;">
            <i class="ti ti-lock"></i>
        </div>
        <h3 id="frozenModalTitle" style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 6px;">
            Fitur Ini Sedang Dibekukan
        </h3>
        <p id="frozenModalMsg" style="font-size: 0.825rem; color: #64748b; line-height: 1.5; margin-bottom: 20px;">
            Menu ini dinonaktifkan sementara waktu oleh pihak manajemen HR.
        </p>
        <button type="button" class="btn btn-primary" style="width: 100%; border-radius: 12px; padding: 10px; font-weight: 700; font-size: 0.85rem;" onclick="document.getElementById('frozenNoticeModal').style.display='none'">
            Mengerti & Tutup
        </button>
    </div>
</div>

<!-- MODAL SEMUA APLIKASI / SEMUA MENU -->
<div id="modalSemuaMenu" class="talenta-sheet-backdrop" onclick="closeSemuaMenuModal(event)">
    <div class="talenta-sheet-content" onclick="event.stopPropagation()" style="max-height: 85vh; overflow-y: auto;">
        <div class="talenta-sheet-handle"></div>
        <div class="talenta-sheet-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="ti ti-layout-grid" style="color: #2563eb; font-size: 1.35rem;"></i>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">Semua Menu & Layanan</h3>
            </div>
            <button type="button" class="talenta-sheet-close" onclick="closeSemuaMenuModal(event)"><i class="ti ti-x"></i></button>
        </div>
        
        <!-- Grid Semua Menu -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px 8px; margin-top: 18px; text-align: center;">
            <!-- Presensi Online -->
            <a href="<?= BASE_URL ?>/operator/absensi.php" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); width: 52px; height: 52px;">
                    <i class="ti ti-map-pin"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem;">Presensi Online</span>
            </a>

            <!-- Jadwal & Kalender -->
            <a href="<?= BASE_URL ?>/operator/jadwal.php" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); width: 52px; height: 52px;">
                    <i class="ti ti-calendar"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem;">Jadwal Shift</span>
            </a>

            <!-- Riwayat Presensi -->
            <a href="<?= BASE_URL ?>/operator/riwayat.php" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%); width: 52px; height: 52px;">
                    <i class="ti ti-calendar-stats"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem;">Riwayat Absen</span>
            </a>

            <!-- Pengajuan Izin / Cuti -->
            <a href="<?= BASE_URL ?>/operator/izin.php" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); width: 52px; height: 52px;">
                    <i class="ti ti-file-text"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem;">Pengajuan Izin</span>
            </a>

            <?php if (in_array($currentUser['role'], ['supervisor', 'admin', 'superadmin'])): ?>
            <!-- Approval (Hanya Supervisor/Admin) -->
            <a href="<?= BASE_URL ?>/supervisor/izin.php" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); width: 52px; height: 52px;">
                    <i class="ti ti-clipboard-check"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem;">Approval</span>
            </a>
            <?php endif; ?>

            <!-- Direktori Karyawan -->
            <a href="<?= BASE_URL ?>/operator/karyawan.php" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); width: 52px; height: 52px;">
                    <i class="ti ti-users"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem;">Karyawan</span>
            </a>

            <!-- Inbox & Notifikasi -->
            <a href="<?= BASE_URL ?>/operator/inbox.php" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); width: 52px; height: 52px;">
                    <i class="ti ti-bell"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem;">Kotak Masuk</span>
            </a>

            <!-- Profil Akun -->
            <a href="<?= BASE_URL ?>/operator/profile.php" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); width: 52px; height: 52px;">
                    <i class="ti ti-user"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem;">Profil Saya</span>
            </a>

            <!-- Logout -->
            <a href="<?= BASE_URL ?>/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?')" class="talenta-app-item">
                <div class="talenta-app-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); width: 52px; height: 52px;">
                    <i class="ti ti-logout"></i>
                </div>
                <span class="talenta-app-label" style="font-size: 0.75rem; color: #ef4444;">Keluar</span>
            </a>
        </div>
    </div>
</div>

<script>
function openSemuaMenuModal(e) {
    if (e) e.preventDefault();
    const m = document.getElementById('modalSemuaMenu');
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
}
function closeSemuaMenuModal(e) {
    if (e) e.stopPropagation();
    const m = document.getElementById('modalSemuaMenu');
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
}

function openFrozenNotice(title, message) {
    const cleanTitle = title.replace(/<[^>]*>?/gm, ' ');
    document.getElementById('frozenModalTitle').innerText = cleanTitle + ' Sedang Dibekukan';
    document.getElementById('frozenModalMsg').innerText = message || 'Menu ini dinonaktifkan sementara waktu oleh pihak manajemen HR.';
    document.getElementById('frozenNoticeModal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

