<?php
// admin/index.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['admin']);

$db = Database::getConnection();
$todayStr = date('Y-m-d');
$activePage = 'admin_dashboard';
$pageTitle = 'Dashboard Admin & Monitoring';
$pageSubtitle = 'Ringkasan operasional dan monitoring kehadiran divisi hari ini (' . Helpers::formatTanggalIndo($todayStr) . ')';

// 1. Hitung Statistik Hari Ini
$totalEmployees = $db->query("SELECT COUNT(*) FROM users WHERE role = 'operator'")->fetchColumn();

// Hadir hari ini
$hadirCount = $db->query("SELECT COUNT(*) FROM attendances WHERE date = '{$todayStr}' AND status IN ('hadir', 'terlambat')")->fetchColumn();

// Terlambat
$terlambatCount = $db->query("SELECT COUNT(*) FROM attendances WHERE date = '{$todayStr}' AND status = 'terlambat'")->fetchColumn();

// Izin / Sakit hari ini
$izinCount = $db->query("
    SELECT COUNT(*) FROM leaves 
    WHERE status = 'approved' AND '{$todayStr}' BETWEEN start_date AND end_date
")->fetchColumn();

// Belum Hadir / Alpha
$belumHadirCount = max(0, $totalEmployees - ($hadirCount + $izinCount));

// 2. Monitoring Per Divisi Hari Ini
$sqlDiv = "
    SELECT 
        d.id, d.name as division_name, d.code as division_code,
        u_spv.full_name as supervisor_name,
        COUNT(u.id) as total_operator,
        COUNT(CASE WHEN a.status IN ('hadir', 'terlambat') THEN 1 END) as hadir_operator,
        COUNT(CASE WHEN s.shift_id = 1 AND a.status IN ('hadir', 'terlambat') THEN 1 END) as hadir_shift1,
        COUNT(CASE WHEN s.shift_id = 2 AND a.status IN ('hadir', 'terlambat') THEN 1 END) as hadir_shift2,
        COUNT(CASE WHEN l.id IS NOT NULL THEN 1 END) as izin_operator
    FROM divisions d
    LEFT JOIN users u_spv ON d.supervisor_id = u_spv.id
    LEFT JOIN users u ON u.division_id = d.id AND u.role = 'operator'
    LEFT JOIN attendances a ON a.user_id = u.id AND a.date = '{$todayStr}'
    LEFT JOIN schedules s ON s.user_id = u.id AND s.date = '{$todayStr}'
    LEFT JOIN leaves l ON l.user_id = u.id AND l.status = 'approved' AND '{$todayStr}' BETWEEN l.start_date AND l.end_date
    GROUP BY d.id
    ORDER BY d.id ASC
";
$divisionStats = $db->query($sqlDiv)->fetchAll();

// 3. Izin Pending yang Perlu Review Segera (Termasuk Surat Dokter)
$sqlPendingLeaves = "
    SELECT l.*, u.full_name, u.position, d.name as division_name,
           spv.full_name as supervisor_name
    FROM leaves l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN users spv ON d.supervisor_id = spv.id
    WHERE l.status = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 5
";
$pendingLeaves = $db->query($sqlPendingLeaves)->fetchAll();

// 4. Log Presensi Terkini Hari Ini
$sqlRecentAtt = "
    SELECT a.*, u.full_name, u.position, d.name as division_name, s_shift.name as shift_name, s_shift.color as shift_color
    FROM attendances a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN schedules s ON s.user_id = u.id AND s.date = a.date
    LEFT JOIN shifts s_shift ON s.shift_id = s_shift.id
    WHERE a.date = '{$todayStr}'
    ORDER BY a.clock_in DESC
    LIMIT 6
";
$recentAttendances = $db->query($sqlRecentAtt)->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<!-- KPI Stat Cards -->
<div class="stats-grid">
    <div class="stat-card" style="--stat-color: #3b82f6; --stat-bg: #eff6ff;">
        <div class="stat-info">
            <div class="stat-label">Total Operator</div>
            <div class="stat-value"><?= $totalEmployees ?></div>
            <div class="stat-sub">Terdaftar di seluruh divisi</div>
        </div>
        <div class="stat-icon"><i class="ti ti-users"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #10b981; --stat-bg: #ecfdf5;">
        <div class="stat-info">
            <div class="stat-label">Hadir Hari Ini</div>
            <div class="stat-value"><?= $hadirCount ?></div>
            <div class="stat-sub"><?= $totalEmployees > 0 ? round(($hadirCount / $totalEmployees) * 100) : 0 ?>% Rasio Kehadiran</div>
        </div>
        <div class="stat-icon"><i class="ti ti-user-check"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #f59e0b; --stat-bg: #fffbeb;">
        <div class="stat-info">
            <div class="stat-label">Terlambat</div>
            <div class="stat-value"><?= $terlambatCount ?></div>
            <div class="stat-sub">Masuk lewat jam shift</div>
        </div>
        <div class="stat-icon"><i class="ti ti-clock-pause"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #ef4444; --stat-bg: #fef2f2;">
        <div class="stat-info">
            <div class="stat-label">Sakit / Izin</div>
            <div class="stat-value"><?= $izinCount ?></div>
            <div class="stat-sub"><?= count($pendingLeaves) ?> Pengajuan Pending</div>
        </div>
        <div class="stat-icon"><i class="ti ti-file-certificate"></i></div>
    </div>

    <div class="stat-card" style="--stat-color: #64748b; --stat-bg: #f8fafc;">
        <div class="stat-info">
            <div class="stat-label">Belum Hadir / Alpha</div>
            <div class="stat-value"><?= $belumHadirCount ?></div>
            <div class="stat-sub">Operator belum clock-in</div>
        </div>
        <div class="stat-icon"><i class="ti ti-user-x"></i></div>
    </div>
</div>

<!-- SECTION MONITORING PER DIVISI -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="ti ti-building-community" style="color: var(--primary);"></i>
            Monitoring Kehadiran Realtime Per Divisi (Hari Ini)
        </div>
        <a href="<?= BASE_URL ?>/admin/monitoring.php" class="btn btn-secondary btn-sm">
            Lihat Rincian Seluruh Karyawan <i class="ti ti-arrow-right"></i>
        </a>
    </div>
    <!-- TAMPILAN KHUSUS MOBILE: KARTU DIVISI -->
    <div class="card-body mobile-only-view" style="padding: 12px;">
        <?php foreach ($divisionStats as $div): 
            $pct = $div['total_operator'] > 0 ? round(($div['hadir_operator'] / $div['total_operator']) * 100) : 0;
        ?>
            <div class="ios-data-card">
                <div class="ios-card-header">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-family: monospace; font-weight: 800; background: #e0e7ff; color: #4338ca; padding: 2px 6px; border-radius: 6px; font-size: 0.75rem;">
                                <?= htmlspecialchars($div['division_code']) ?>
                            </span>
                            <div style="font-weight: 800; font-size: 0.95rem; color: #0f172a;">
                                <?= htmlspecialchars($div['division_name']) ?>
                            </div>
                        </div>
                        <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px;">
                            SPV: <?= $div['supervisor_name'] ? htmlspecialchars($div['supervisor_name']) : '<span style="font-style:italic;">Belum ada</span>' ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-weight: 800; font-size: 0.9rem; color: <?= $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444') ?>;">
                            <?= $pct ?>%
                        </span>
                        <div style="font-size: 0.68rem; color: #64748b;"><?= $div['hadir_operator'] ?>/<?= $div['total_operator'] ?> Hadir</div>
                    </div>
                </div>

                <!-- Progress bar -->
                <div style="height: 6px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
                    <div style="width: <?= $pct ?>%; height: 100%; background: <?= $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444') ?>; border-radius: 999px;"></div>
                </div>

                <!-- Shift breakdown & detail button -->
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <span class="badge-shift" style="background: #e0f2fe; color: #0284c7; padding: 2px 8px; font-size: 0.7rem;">
                            <i class="ti ti-sun"></i> S1: <?= $div['hadir_shift1'] ?>
                        </span>
                        <span class="badge-shift" style="background: #f3e8ff; color: #7c3aed; padding: 2px 8px; font-size: 0.7rem;">
                            <i class="ti ti-moon"></i> S2: <?= $div['hadir_shift2'] ?>
                        </span>
                        <?php if ($div['izin_operator'] > 0): ?>
                            <span class="status-badge status-sakit" style="padding: 2px 8px; font-size: 0.7rem;">
                                Izin: <?= $div['izin_operator'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= BASE_URL ?>/admin/monitoring.php?division_id=<?= $div['id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 8px;">
                        Detail <i class="ti ti-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TAMPILAN KHUSUS DESKTOP (TABLE) -->
    <div class="card-body desktop-only-view">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Divisi</th>
                        <th>Supervisor Penanggung Jawab</th>
                        <th style="text-align: center;">Total Karyawan</th>
                        <th>Progress Kehadiran Hari Ini</th>
                        <th style="text-align: center;">Shift 1</th>
                        <th style="text-align: center;">Shift 2</th>
                        <th style="text-align: center;">Izin/Sakit</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($divisionStats as $div): 
                        $pct = $div['total_operator'] > 0 ? round(($div['hadir_operator'] / $div['total_operator']) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($div['division_name']) ?></strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">[<?= htmlspecialchars($div['division_code']) ?>]</div>
                        </td>
                        <td>
                            <?php if ($div['supervisor_name']): ?>
                                <span style="font-weight: 500;"><i class="ti ti-user-check" style="color: #10b981;"></i> <?= htmlspecialchars($div['supervisor_name']) ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">Belum ditugaskan</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; font-weight: 700; font-size: 1rem;">
                            <?= $div['total_operator'] ?>
                        </td>
                        <td style="min-width: 180px;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 600; margin-bottom: 4px;">
                                <span><?= $div['hadir_operator'] ?> / <?= $div['total_operator'] ?> Hadir</span>
                                <span><?= $pct ?>%</span>
                            </div>
                            <div style="height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
                                <div style="width: <?= $pct ?>%; height: 100%; background: <?= $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444') ?>; border-radius: 999px;"></div>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge-shift" style="background: #e0f2fe; color: #0284c7;">
                                <i class="ti ti-sun"></i> <?= $div['hadir_shift1'] ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge-shift" style="background: #f3e8ff; color: #7c3aed;">
                                <i class="ti ti-moon"></i> <?= $div['hadir_shift2'] ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($div['izin_operator'] > 0): ?>
                                <span class="status-badge status-sakit"><?= $div['izin_operator'] ?> Orang</span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">0</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <a href="<?= BASE_URL ?>/admin/monitoring.php?division_id=<?= $div['id'] ?>" class="btn btn-secondary btn-sm" title="Pantau Divisi">
                                <i class="ti ti-eye"></i> Detail
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SECTION 2 KOLOM: APPROVAL IZIN PENDING & PRESENSI REALTIME -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
    <!-- Kolom 1: Pengajuan Izin Menunggu Review (Ada Surat Dokter) -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="ti ti-file-certificate" style="color: #ef4444;"></i>
                Pengajuan Izin & Sakit (Perlu Persetujuan)
            </div>
            <a href="<?= BASE_URL ?>/admin/izin.php" class="btn btn-secondary btn-sm">Semua Izin</a>
        </div>
        <div class="card-body">
            <?php if (empty($pendingLeaves)): ?>
                <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                    <i class="ti ti-check-circle" style="font-size: 2.5rem; color: #10b981; display: block; margin-bottom: 8px;"></i>
                    Tidak ada pengajuan izin yang tertunda saat ini.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Jenis</th>
                                <th>Tanggal</th>
                                <th>Surat Dokter</th>
                                <th>Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingLeaves as $lv): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($lv['full_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($lv['division_name']) ?></div>
                                </td>
                                <td><?= Helpers::statusBadge($lv['type']) ?></td>
                                <td>
                                    <div style="font-size: 0.8125rem;">
                                        <?= date('d M', strtotime($lv['start_date'])) ?> s/d <?= date('d M Y', strtotime($lv['end_date'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($lv['doctor_letter']): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($lv['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($lv['full_name'])) ?>', '<?= $lv['start_date'] ?>')" style="color: #dc2626;">
                                            <i class="ti ti-file-search"></i> Cek Surat
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.75rem;">Tidak ada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/izin.php?action=review&id=<?= $lv['id'] ?>" class="btn btn-primary btn-sm">
                                        Proses
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kolom 2: Aktivitas Presensi Terbaru Hari Ini -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="ti ti-clock" style="color: var(--primary);"></i>
                Log Clock-in Terbaru Hari Ini
            </div>
            <a href="<?= BASE_URL ?>/admin/monitoring.php" class="btn btn-secondary btn-sm">Live Feed</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentAttendances)): ?>
                <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                    <i class="ti ti-clock-off" style="font-size: 2.5rem; color: #94a3b8; display: block; margin-bottom: 8px;"></i>
                    Belum ada aktivitas clock-in hari ini.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Jam Masuk</th>
                                <th>Shift</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttendances as $att): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($att['full_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($att['division_name']) ?></div>
                                </td>
                                <td>
                                    <span style="font-weight: 700; font-family: monospace; color: var(--text-heading);">
                                        <?= htmlspecialchars(substr($att['clock_in'], 0, 5)) ?> WIB
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-shift" style="background: <?= htmlspecialchars($att['shift_color']) ?>15; color: <?= htmlspecialchars($att['shift_color']) ?>;">
                                        <?= htmlspecialchars($att['shift_name'] ?? 'Shift 1') ?>
                                    </span>
                                </td>
                                <td><?= Helpers::statusBadge($att['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
