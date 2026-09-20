<?php
// supervisor/index.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['supervisor', 'admin']);

$db = Database::getConnection();
$currentUser = Auth::user();

// Ambil daftar divisi untuk selector jika diakses Superadmin/Admin
$allDivisions = $db->query("SELECT * FROM divisions ORDER BY id ASC")->fetchAll();
$divId = isset($_GET['division_id']) ? (int)$_GET['division_id'] : (int)($currentUser['division_id'] ?: ($allDivisions[0]['id'] ?? 1));

$currentDivName = 'Divisi';
foreach ($allDivisions as $d) {
    if ($d['id'] == $divId) {
        $currentDivName = $d['name'];
        break;
    }
}

$todayStr = date('Y-m-d');
$activePage = 'spv_dashboard';
$pageTitle = 'Dashboard Supervisor';
$pageSubtitle = 'Pemantauan operasional dan kehadiran tim Divisi ' . htmlspecialchars($currentDivName);

// Ambil anggota tim operator divisi ini (dengan prepared statement aman)
$stmtTeam = $db->prepare("SELECT COUNT(*) FROM users WHERE division_id = ? AND role = 'operator'");
$stmtTeam->execute([$divId]);
$totalTeam = $stmtTeam->fetchColumn();

// Hadir hari ini
$sqlHadir = "
    SELECT COUNT(*) FROM attendances a
    JOIN users u ON a.user_id = u.id
    WHERE u.division_id = ? AND a.date = ? AND a.status IN ('hadir', 'terlambat')
";
$stmtHadir = $db->prepare($sqlHadir);
$stmtHadir->execute([$divId, $todayStr]);
$hadirCount = $stmtHadir->fetchColumn();

// Shift 1 vs Shift 2 hari ini
$sqlShift1 = "
    SELECT COUNT(*) FROM schedules s
    JOIN users u ON s.user_id = u.id
    JOIN attendances a ON a.user_id = u.id AND a.date = ?
    WHERE u.division_id = ? AND s.date = ? AND s.shift_id = 1
";
$stmtShift1 = $db->prepare($sqlShift1);
$stmtShift1->execute([$todayStr, $divId, $todayStr]);
$shift1Count = $stmtShift1->fetchColumn();

$sqlShift2 = "
    SELECT COUNT(*) FROM schedules s
    JOIN users u ON s.user_id = u.id
    JOIN attendances a ON a.user_id = u.id AND a.date = ?
    WHERE u.division_id = ? AND s.date = ? AND s.shift_id = 2
";
$stmtShift2 = $db->prepare($sqlShift2);
$stmtShift2->execute([$todayStr, $divId, $todayStr]);
$shift2Count = $stmtShift2->fetchColumn();

// Pengajuan izin tim yang pending
$sqlPending = "
    SELECT l.*, u.full_name, u.position
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    WHERE u.division_id = ? AND l.status = 'pending'
    ORDER BY l.created_at DESC
";
$stmtPending = $db->prepare($sqlPending);
$stmtPending->execute([$divId]);
$pendingLeaves = $stmtPending->fetchAll();

// Presensi hari ini tim
$sqlTeamAtt = "
    SELECT u.id, u.full_name, u.position, sh.name as shift_name, sh.color as shift_color,
           a.clock_in, a.clock_out, a.status as attendance_status,
           l.type as leave_type, l.reason as leave_reason, l.doctor_letter
    FROM users u
    LEFT JOIN schedules s ON s.user_id = u.id AND s.date = ?
    LEFT JOIN shifts sh ON s.shift_id = sh.id
    LEFT JOIN attendances a ON a.user_id = u.id AND a.date = ?
    LEFT JOIN leaves l ON l.user_id = u.id AND l.status = 'approved' AND ? BETWEEN l.start_date AND l.end_date
    WHERE u.division_id = ? AND u.role = 'operator'
    ORDER BY u.full_name ASC
";
$stmtTeamAtt = $db->prepare($sqlTeamAtt);
$stmtTeamAtt->execute([$todayStr, $todayStr, $todayStr, $divId]);
$teamToday = $stmtTeamAtt->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<!-- KPI Stats Divisi -->
<div class="stats-grid">
    <div class="stat-card" style="--stat-color: #4f46e5; --stat-bg: #eef2ff;">
        <div class="stat-info">
            <div class="stat-label">Anggota Tim</div>
            <div class="stat-value"><?= $totalTeam ?></div>
            <div class="stat-sub">Divisi <?= htmlspecialchars($currentUser['division_name'] ?? '') ?></div>
        </div>
        <div class="stat-icon"><i class="ti ti-users"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #10b981; --stat-bg: #ecfdf5;">
        <div class="stat-info">
            <div class="stat-label">Hadir Hari Ini</div>
            <div class="stat-value"><?= $hadirCount ?></div>
            <div class="stat-sub"><?= $totalTeam > 0 ? round(($hadirCount / $totalTeam) * 100) : 0 ?>% Kehadiran Tim</div>
        </div>
        <div class="stat-icon"><i class="ti ti-user-check"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #0284c7; --stat-bg: #e0f2fe;">
        <div class="stat-info">
            <div class="stat-label">Shift 1</div>
            <div class="stat-value"><?= $shift1Count ?></div>
            <div class="stat-sub">07:00 - 15:00 WIB</div>
        </div>
        <div class="stat-icon"><i class="ti ti-sun"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #7c3aed; --stat-bg: #f3e8ff;">
        <div class="stat-info">
            <div class="stat-label">Shift 2</div>
            <div class="stat-value"><?= $shift2Count ?></div>
            <div class="stat-sub">15:00 - 23:00 WIB</div>
        </div>
        <div class="stat-icon"><i class="ti ti-moon"></i></div>
    </div>

    <a href="<?= BASE_URL ?>/supervisor/izin.php" class="stat-card" style="--stat-color: #ef4444; --stat-bg: #fef2f2; text-decoration: none; cursor: pointer;" title="Klik untuk membuka Menu Approval Izin">
        <div class="stat-info">
            <div class="stat-label" style="display: flex; align-items: center; gap: 4px;">
                <span>Izin Perlu Review</span>
                <i class="ti ti-arrow-right" style="font-size: 0.8rem; color: #ef4444;"></i>
            </div>
            <div class="stat-value" style="color: #b91c1c;"><?= count($pendingLeaves) ?></div>
            <div class="stat-sub">Menunggu persetujuan Anda</div>
        </div>
        <div class="stat-icon"><i class="ti ti-checklist"></i></div>
    </a>
</div>

<!-- MENU PINTAS SUPERVISOR & LAYANAN MANDIRI (QUICK ACTIONS) -->
<div style="background: #ffffff; border-radius: 18px; padding: 18px 20px; border: 1px solid rgba(226, 232, 240, 0.85); box-shadow: 0 4px 16px rgba(0,0,0,0.03); margin-bottom: 20px;">
    <div style="font-size: 0.8rem; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
        <span style="display: flex; align-items: center; gap: 6px;">
            <i class="ti ti-apps" style="color: var(--primary);"></i> Menu Operasional & Layanan Mandiri Supervisor
        </span>
        <span style="font-size: 0.675rem; color: #4338ca; font-weight: 800; background: #e0e7ff; padding: 3px 8px; border-radius: 6px;">
            Divisi <?= htmlspecialchars($currentDivName) ?>
        </span>
    </div>

    <div class="talenta-mobile-grid" style="grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));">
        <!-- 1. APPROVAL IZIN (HIGHLIGHTED) -->
        <a href="<?= BASE_URL ?>/supervisor/izin.php" class="talenta-grid-tile" style="position: relative; border-color: <?= !empty($pendingLeaves) ? '#fca5a5' : '#c7d2fe' ?>; background: <?= !empty($pendingLeaves) ? 'linear-gradient(180deg, #fff1f2 0%, #ffffff 100%)' : '#ffffff' ?>;">
            <?php if (!empty($pendingLeaves)): ?>
                <span style="position: absolute; top: 4px; right: 5px; font-size: 0.55rem; font-weight: 800; background: #ef4444; color: #ffffff; padding: 1px 6px; border-radius: 999px; box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35); z-index: 2;">
                    <?= count($pendingLeaves) ?> Pending
                </span>
            <?php endif; ?>
            <div class="talenta-tile-icon tile-pastel-rose">
                <i class="ti ti-file-certificate"></i>
            </div>
            <div class="talenta-tile-title" style="color: #9f1239; font-weight: 800;">Approval<br>Izin Tim</div>
        </a>

        <!-- 2. ATUR JADWAL SHIFT -->
        <a href="<?= BASE_URL ?>/supervisor/jadwal.php" class="talenta-grid-tile">
            <div class="talenta-tile-icon tile-pastel-blue">
                <i class="ti ti-calendar-event"></i>
            </div>
            <div class="talenta-tile-title">Atur Jadwal<br>Shift Tim</div>
        </a>

        <!-- 3. MONITORING LIVE -->
        <a href="<?= BASE_URL ?>/supervisor/monitoring.php" class="talenta-grid-tile">
            <div class="talenta-tile-icon tile-pastel-cyan">
                <i class="ti ti-eye-check"></i>
            </div>
            <div class="talenta-tile-title">Monitoring<br>Live Presensi</div>
        </a>

        <!-- 4. PRESENSI SAYA (CLOCK) -->
        <a href="<?= BASE_URL ?>/operator/index.php" class="talenta-grid-tile">
            <div class="talenta-tile-icon tile-pastel-emerald">
                <i class="ti ti-fingerprint"></i>
            </div>
            <div class="talenta-tile-title">Presensi Saya<br>(Clock In/Out)</div>
        </a>

        <!-- 5. JADWAL SAYA -->
        <a href="<?= BASE_URL ?>/operator/jadwal.php" class="talenta-grid-tile">
            <div class="talenta-tile-icon tile-pastel-violet">
                <i class="ti ti-calendar"></i>
            </div>
            <div class="talenta-tile-title">Jadwal Shift<br>Saya Sendiri</div>
        </a>

        <!-- 6. DIREKTORI KARYAWAN -->
        <a href="<?= BASE_URL ?>/operator/karyawan.php" class="talenta-grid-tile">
            <div class="talenta-tile-icon tile-pastel-amber">
                <i class="ti ti-users"></i>
            </div>
            <div class="talenta-tile-title">Direktori<br>Karyawan</div>
        </a>
    </div>
</div>

<!-- SECTION DAFTAR PENGAJUAN IZIN TIM (DENGAN SURAT DOKTER) -->
<?php if (!empty($pendingLeaves)): ?>
<div class="card" style="border-left: 4px solid #ef4444; margin-bottom: 20px;">
    <div class="card-header">
        <div class="card-title" style="color: #b91c1c;">
            <i class="ti ti-alert-circle"></i>
            Pengajuan Izin Anggota Tim Membutuhkan Persetujuan Anda (<?= count($pendingLeaves) ?>)
        </div>
        <a href="<?= BASE_URL ?>/supervisor/izin.php" class="btn btn-primary btn-sm">Buka Menu Approval</a>
    </div>
<?php else: ?>
<div class="card" style="border-left: 4px solid #10b981; margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 42px; height: 42px; border-radius: 12px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                <i class="ti ti-circle-check"></i>
            </div>
            <div>
                <div style="font-size: 0.925rem; font-weight: 800; color: #0f172a;">Semua Pengajuan Izin Telah Ditinjau</div>
                <div style="font-size: 0.775rem; color: #64748b;">Tidak ada pengajuan izin anggota tim yang pending saat ini.</div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/supervisor/izin.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
            <i class="ti ti-file-certificate"></i> Buka Menu Approval Izin
        </a>
    </div>
</div>
<?php endif; ?>
<?php if (!empty($pendingLeaves)): ?>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Anggota</th>
                        <th>Kategori</th>
                        <th>Tanggal</th>
                        <th>Alasan</th>
                        <th style="text-align: center;">Surat Dokter</th>
                        <th>Target Approval</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingLeaves as $pl): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($pl['full_name']) ?></strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($pl['position']) ?></div>
                        </td>
                        <td><?= Helpers::statusBadge($pl['type']) ?></td>
                        <td><?= date('d/m', strtotime($pl['start_date'])) ?> s/d <?= date('d/m/Y', strtotime($pl['end_date'])) ?></td>
                        <td><?= htmlspecialchars($pl['reason']) ?></td>
                        <td style="text-align: center;">
                            <?php if ($pl['doctor_letter']): ?>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($pl['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($pl['full_name'])) ?>', '<?= $pl['start_date'] ?>')" style="color: #dc2626;">
                                    <i class="ti ti-file-certificate"></i> Lihat Surat
                                </button>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-size: 0.75rem;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="user-role-badge <?= htmlspecialchars($pl['approval_target']) ?>">
                                <?= strtoupper($pl['approval_target']) ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <a href="<?= BASE_URL ?>/supervisor/izin.php" class="btn btn-primary btn-sm">
                                Review & Putuskan
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- SECTION KEHADIRAN TIM HARI INI -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="ti ti-users-group" style="color: var(--primary);"></i>
            Status Kehadiran Anggota Tim Hari Ini (<?= Helpers::formatTanggalIndo($todayStr) ?>)
        </div>
        <a href="<?= BASE_URL ?>/supervisor/jadwal.php" class="btn btn-secondary btn-sm">
            <i class="ti ti-calendar"></i> Atur Shift Tim
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Operator</th>
                        <th>Posisi</th>
                        <th>Jadwal Shift</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status Presensi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teamToday as $tm): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($tm['full_name']) ?></strong></td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($tm['position']) ?></td>
                        <td>
                            <?php if ($tm['shift_name']): ?>
                                <span class="badge-shift" style="background: <?= htmlspecialchars($tm['shift_color']) ?>15; color: <?= htmlspecialchars($tm['shift_color']) ?>;">
                                    <?= htmlspecialchars($tm['shift_name']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">Belum diatur</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $tm['clock_in'] ? '<strong style="font-family: monospace;">' . htmlspecialchars($tm['clock_in']) . '</strong>' : '<span style="color:#94a3b8;">-</span>' ?>
                        </td>
                        <td>
                            <?= $tm['clock_out'] ? '<strong style="font-family: monospace;">' . htmlspecialchars($tm['clock_out']) . '</strong>' : '<span style="color:#94a3b8;">-</span>' ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($tm['attendance_status'])) {
                                echo Helpers::statusBadge($tm['attendance_status']);
                            } elseif (!empty($tm['leave_type'])) {
                                echo Helpers::statusBadge($tm['leave_type']);
                            } else {
                                echo Helpers::statusBadge('tidak_hadir');
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
