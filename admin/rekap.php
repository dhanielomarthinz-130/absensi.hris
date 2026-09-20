<?php
// admin/rekap.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['admin']);

$db = Database::getConnection();
$activePage = 'admin_rekap';
$pageTitle = 'Rekapitulasi Kehadiran & Laporan';

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$divisionFilter = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? (int)$_GET['division_id'] : null;

$divisions = $db->query("SELECT * FROM divisions ORDER BY id ASC")->fetchAll();

// Hitung rekap per operator untuk bulan terpilih
$sql = "
    SELECT 
        u.id, u.full_name, u.position,
        d.name as division_name,
        COUNT(DISTINCT s.date) as total_jadwal,
        COUNT(DISTINCT CASE WHEN a.status IN ('hadir', 'terlambat') THEN a.date END) as total_hadir,
        COUNT(DISTINCT CASE WHEN a.status = 'terlambat' THEN a.date END) as total_terlambat,
        COUNT(DISTINCT CASE WHEN l.type = 'sakit' AND l.status = 'approved' THEN s.date END) as total_sakit,
        COUNT(DISTINCT CASE WHEN l.type = 'izin' AND l.status = 'approved' THEN s.date END) as total_izin
    FROM users u
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN schedules s ON s.user_id = u.id AND strftime('%Y-%m', s.date) = '{$month}' AND s.shift_id != 3
    LEFT JOIN attendances a ON a.user_id = u.id AND strftime('%Y-%m', a.date) = '{$month}'
    LEFT JOIN leaves l ON l.user_id = u.id AND s.date BETWEEN l.start_date AND l.end_date
    WHERE u.role = 'operator'
";

if ($divisionFilter) {
    $sql .= " AND u.division_id = {$divisionFilter}";
}

$sql .= " GROUP BY u.id ORDER BY d.id ASC, u.full_name ASC";
$reports = $db->query($sql)->fetchAll();

require_once __DIR__ . '/../templates/header.php';
?>

<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Pilih Periode Bulan</label>
                <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>" onchange="this.form.submit()">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Divisi</label>
                <select name="division_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Divisi</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $divisionFilter == $d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="ti ti-printer"></i> Cetak Dokumen
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="ti ti-report-analytics" style="color: var(--primary);"></i>
            Laporan Rekapitulasi Presensi Periode <?= date('F Y', strtotime($month . '-01')) ?>
        </div>
    </div>
    <!-- TAMPILAN KHUSUS MOBILE: KARTU REKAP KARYAWAN -->
    <div class="card-body mobile-only-view" style="padding: 12px;">
        <?php if (empty($reports)): ?>
            <div style="text-align: center; padding: 32px 16px; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1; color: #64748b;">
                <i class="ti ti-report-off" style="font-size: 2rem; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
                Tidak ada data rekap presensi pada periode ini.
            </div>
        <?php endif; ?>

        <?php foreach ($reports as $r): 
            $pct = $r['total_jadwal'] > 0 ? round(($r['total_hadir'] / $r['total_jadwal']) * 100) : 0;
            $pctColor = $pct >= 80 ? '#10b981' : ($pct >= 60 ? '#f59e0b' : '#ef4444');
            $pctBg = $pct >= 80 ? '#f0fdf4' : ($pct >= 60 ? '#fffbeb' : '#fef2f2');
        ?>
            <div class="ios-data-card">
                <div class="ios-card-header">
                    <div class="ios-card-user">
                        <div class="ios-card-avatar">
                            <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                        </div>
                        <div style="min-width: 0;">
                            <div class="ios-card-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($r['full_name']) ?>
                            </div>
                            <div class="ios-card-sub">
                                <span class="user-role-badge" style="background: #f1f5f9; color: #475569; padding: 1px 6px; font-size: 0.65rem; border-radius: 4px;">
                                    <?= htmlspecialchars($r['division_name']) ?>
                                </span>
                                <span style="font-size: 0.72rem;"><?= htmlspecialchars($r['position']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div style="background: <?= $pctBg ?>; color: <?= $pctColor ?>; font-weight: 800; font-size: 0.85rem; padding: 4px 10px; border-radius: 999px; border: 1px solid <?= $pctColor ?>30; flex-shrink: 0;">
                        <?= $pct ?>% Hadir
                    </div>
                </div>

                <div class="ios-grid-4">
                    <div class="ios-pill-box">
                        <span class="ios-pill-label">Total Jadwal</span>
                        <span class="ios-pill-val"><?= $r['total_jadwal'] ?> Hari</span>
                    </div>
                    <div class="ios-pill-box">
                        <span class="ios-pill-label">Total Hadir</span>
                        <span class="ios-pill-val" style="color: #10b981;"><?= $r['total_hadir'] ?> Hari</span>
                    </div>
                    <div class="ios-pill-box">
                        <span class="ios-pill-label">Terlambat</span>
                        <span class="ios-pill-val" style="color: #f59e0b;"><?= $r['total_terlambat'] ?> Kali</span>
                    </div>
                    <div class="ios-pill-box">
                        <span class="ios-pill-label">Sakit / Izin</span>
                        <span class="ios-pill-val" style="color: #6366f1;"><?= ($r['total_sakit'] + $r['total_izin']) ?> Hari</span>
                    </div>
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
                        <th>Karyawan</th>
                        <th>Divisi</th>
                        <th style="text-align: center;">Total Jadwal Shift</th>
                        <th style="text-align: center;">Total Hadir</th>
                        <th style="text-align: center;">Terlambat</th>
                        <th style="text-align: center;">Sakit</th>
                        <th style="text-align: center;">Izin</th>
                        <th style="text-align: center;">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): 
                        $pct = $r['total_jadwal'] > 0 ? round(($r['total_hadir'] / $r['total_jadwal']) * 100) : 0;
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($r['position']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($r['division_name']) ?></td>
                            <td style="text-align: center; font-weight: 600;"><?= $r['total_jadwal'] ?> Hari</td>
                            <td style="text-align: center; font-weight: 700; color: #10b981;"><?= $r['total_hadir'] ?> Hari</td>
                            <td style="text-align: center; color: #f59e0b;"><?= $r['total_terlambat'] ?> Kali</td>
                            <td style="text-align: center; color: #ef4444;"><?= $r['total_sakit'] ?> Hari</td>
                            <td style="text-align: center; color: #3b82f6;"><?= $r['total_izin'] ?> Hari</td>
                            <td style="text-align: center;">
                                <span style="font-weight: 800; color: <?= $pct >= 80 ? '#10b981' : ($pct >= 60 ? '#f59e0b' : '#ef4444') ?>;">
                                    <?= $pct ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
