<?php
// admin/monitoring.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

Auth::requireRole(['admin']);

$db = Database::getConnection();
$activePage = 'admin_monitoring';

$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$divisionFilter = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? (int)$_GET['division_id'] : null;
$shiftFilter = isset($_GET['shift_id']) && $_GET['shift_id'] !== '' ? (int)$_GET['shift_id'] : null;
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

$divisions = $db->query("SELECT * FROM divisions ORDER BY id ASC")->fetchAll();
$shifts = $db->query("SELECT * FROM shifts ORDER BY id ASC")->fetchAll();

// PROSES POST: Adjust / Koreksi Presensi oleh Superadmin & Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_attendance') {
    $targetUserId = (int)$_POST['user_id'];
    $targetDate = $_POST['date'];
    $clockIn = !empty($_POST['clock_in']) ? trim($_POST['clock_in']) : null;
    $clockOut = !empty($_POST['clock_out']) ? trim($_POST['clock_out']) : null;
    $status = $_POST['status'] ?? 'hadir';
    $notes = trim($_POST['notes'] ?? '');

    $adjusterName = Auth::user()['full_name'];
    $fullNote = $notes ? "{$notes} [Adjusted by {$adjusterName}]" : "[Adjusted by {$adjusterName}]";

    $stmt = $db->prepare("
        INSERT INTO attendances (user_id, date, clock_in, clock_out, status, notes)
        VALUES (?, ?, ?, ?, ?, ?)
        ON CONFLICT(user_id, date) DO UPDATE SET 
            clock_in = excluded.clock_in,
            clock_out = excluded.clock_out,
            status = excluded.status,
            notes = excluded.notes
    ");
    $stmt->execute([$targetUserId, $targetDate, $clockIn, $clockOut, $status, $fullNote]);

    Helpers::setFlash('success', "Data presensi berhasil disesuaikan / di-adjust oleh {$adjusterName}.");
    header("Location: " . BASE_URL . "/admin/monitoring.php?date={$targetDate}" . ($divisionFilter ? "&division_id={$divisionFilter}" : ""));
    exit;
}

// Query monitoring karyawan & status presensi
$sql = "
    SELECT 
        u.id as user_id, u.full_name, u.position,
        d.id as division_id, d.name as division_name, d.code as division_code,
        spv.full_name as supervisor_name,
        s.shift_id, sh.name as shift_name, sh.start_time, sh.end_time, sh.color as shift_color,
        a.clock_in, a.clock_out, a.status as attendance_status, a.notes as attendance_notes,
        l.type as leave_type, l.reason as leave_reason, l.doctor_letter, l.status as leave_status
    FROM users u
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN users spv ON d.supervisor_id = spv.id
    LEFT JOIN schedules s ON s.user_id = u.id AND s.date = '{$selectedDate}'
    LEFT JOIN shifts sh ON s.shift_id = sh.id
    LEFT JOIN attendances a ON a.user_id = u.id AND a.date = '{$selectedDate}'
    LEFT JOIN leaves l ON l.user_id = u.id AND l.status = 'approved' AND '{$selectedDate}' BETWEEN l.start_date AND l.end_date
    WHERE u.role = 'operator'
";

if ($divisionFilter) {
    $sql .= " AND u.division_id = {$divisionFilter}";
}
if ($shiftFilter) {
    $sql .= " AND s.shift_id = {$shiftFilter}";
}

$sql .= " ORDER BY d.id ASC, u.full_name ASC";
$rows = $db->query($sql)->fetchAll();

// Filter status jika ada
if ($statusFilter) {
    $rows = array_filter($rows, function($r) use ($statusFilter) {
        if ($statusFilter === 'hadir') return $r['attendance_status'] === 'hadir';
        if ($statusFilter === 'terlambat') return $r['attendance_status'] === 'terlambat';
        if ($statusFilter === 'sakit') return $r['leave_type'] === 'sakit';
        if ($statusFilter === 'izin') return in_array($r['leave_type'], ['izin', 'cuti']);
        if ($statusFilter === 'alpha') return empty($r['attendance_status']) && empty($r['leave_type']);
        return true;
    });
}

// Rekap angka untuk header
$totalFiltered = count($rows);
$totalHadir = count(array_filter($rows, fn($r) => in_array($r['attendance_status'], ['hadir', 'terlambat'])));
$totalTerlambat = count(array_filter($rows, fn($r) => $r['attendance_status'] === 'terlambat'));
$totalSakitIzin = count(array_filter($rows, fn($r) => !empty($r['leave_type'])));
$totalAlpha = max(0, $totalFiltered - ($totalHadir + $totalSakitIzin));

$pageTitle = 'Monitoring Kehadiran Divisi';
$pageSubtitle = "Status kehadiran real-time per divisi pada tanggal " . Helpers::formatTanggalIndo($selectedDate);

require_once __DIR__ . '/../templates/header.php';
?>

<!-- Filter & Tool Bar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="" class="filter-form-grid">
            <div class="form-group filter-item">
                <label class="form-label">Pilih Tanggal</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
            </div>

            <div class="form-group filter-item">
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

            <div class="form-group filter-item">
                <label class="form-label">Shift</label>
                <select name="shift_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Shift</option>
                    <?php foreach ($shifts as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $shiftFilter == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filter-item">
                <label class="form-label">Status Kehadiran</label>
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="hadir" <?= $statusFilter === 'hadir' ? 'selected' : '' ?>>Hadir (Tepat Waktu)</option>
                    <option value="terlambat" <?= $statusFilter === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    <option value="sakit" <?= $statusFilter === 'sakit' ? 'selected' : '' ?>>Sakit (Surat Dokter)</option>
                    <option value="izin" <?= $statusFilter === 'izin' ? 'selected' : '' ?>>Izin / Cuti</option>
                    <option value="alpha" <?= $statusFilter === 'alpha' ? 'selected' : '' ?>>Alpha / Belum Hadir</option>
                </select>
            </div>

            <div class="filter-action-item">
                <a href="<?= BASE_URL ?>/admin/monitoring.php" class="btn btn-secondary filter-reset-btn" title="Reset Filter">
                    <i class="ti ti-refresh"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan Filter Bar (Chips) -->
<div class="filter-chips-scroll">
    <div class="chip-item chip-default">
        Total: <strong><?= $totalFiltered ?> Karyawan</strong>
    </div>
    <div class="chip-item chip-success">
        Hadir: <strong><?= $totalHadir ?> Orang</strong>
    </div>
    <div class="chip-item chip-warning">
        Terlambat: <strong><?= $totalTerlambat ?> Orang</strong>
    </div>
    <div class="chip-item chip-danger">
        Sakit/Izin: <strong><?= $totalSakitIzin ?> Orang</strong>
    </div>
    <div class="chip-item chip-slate">
        Belum Clock-in: <strong><?= $totalAlpha ?> Orang</strong>
    </div>
</div>

<!-- Data Table Monitoring -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="ti ti-list-check" style="color: var(--primary);"></i>
            Daftar Kehadiran Operator Per Divisi
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
            <i class="ti ti-printer"></i> Cetak Laporan
        </button>
    </div>
    <!-- TAMPILAN KHUSUS MOBILE (IOS / ANDROID CARD LIST) -->
    <div class="card-body mobile-only-view" style="padding: 12px;">
        <?php if (empty($rows)): ?>
            <div style="text-align: center; padding: 32px 16px; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1; color: #64748b;">
                <i class="ti ti-user-off" style="font-size: 2rem; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
                Tidak ditemukan data karyawan yang sesuai dengan kriteria filter.
            </div>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
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
                    <div>
                        <?php 
                        if (!empty($r['attendance_status'])) {
                            echo Helpers::statusBadge($r['attendance_status']);
                        } elseif (!empty($r['leave_type'])) {
                            echo Helpers::statusBadge($r['leave_type']);
                        } else {
                            echo Helpers::statusBadge('tidak_hadir');
                        }
                        ?>
                    </div>
                </div>

                <!-- Shift & SPV info -->
                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 8px 12px; border-radius: 10px; font-size: 0.775rem;">
                    <div>
                        <span style="color: #64748b; font-size: 0.7rem; font-weight: 700;">SHIFT: </span>
                        <?php if ($r['shift_name']): ?>
                            <span style="font-weight: 700; color: <?= htmlspecialchars($r['shift_color']) ?>;">
                                <?= htmlspecialchars($r['shift_name']) ?> (<?= htmlspecialchars($r['start_time']) ?> - <?= htmlspecialchars($r['end_time']) ?>)
                            </span>
                        <?php else: ?>
                            <span style="color: #94a3b8;">Belum diatur</span>
                        <?php endif; ?>
                    </div>
                    <div style="color: #64748b; font-size: 0.72rem;">
                        SPV: <strong><?= htmlspecialchars($r['supervisor_name'] ?? '-') ?></strong>
                    </div>
                </div>

                <!-- Clock In & Clock Out Pills -->
                <div class="ios-grid-2">
                    <div class="ios-pill-box">
                        <span class="ios-pill-label"><i class="ti ti-login" style="color: #10b981;"></i> Clock In</span>
                        <span class="ios-pill-val" style="font-family: monospace;">
                            <?= $r['clock_in'] ? htmlspecialchars($r['clock_in']) : '<span style="color:#94a3b8; font-weight:normal;">--:--:--</span>' ?>
                        </span>
                    </div>
                    <div class="ios-pill-box">
                        <span class="ios-pill-label"><i class="ti ti-logout" style="color: #ef4444;"></i> Clock Out</span>
                        <span class="ios-pill-val" style="font-family: monospace;">
                            <?= $r['clock_out'] ? htmlspecialchars($r['clock_out']) : '<span style="color:#94a3b8; font-weight:normal;">--:--:--</span>' ?>
                        </span>
                    </div>
                </div>

                <!-- Keterangan / Dokumen Surat Dokter -->
                <?php if (!empty($r['leave_type']) || $r['attendance_notes'] || $r['doctor_letter']): ?>
                    <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 10px; padding: 8px 10px; font-size: 0.775rem; color: #92400e;">
                        <?php if (!empty($r['leave_type'])): ?>
                            <strong>Keterangan Izin:</strong> <?= htmlspecialchars($r['leave_reason']) ?>
                            <?php if ($r['doctor_letter']): ?>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($r['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($r['full_name'])) ?>', '<?= $selectedDate ?>')" style="padding: 3px 8px; font-size: 0.7rem; margin-top: 4px; display: block; color: #dc2626;">
                                    <i class="ti ti-file-certificate"></i> Lihat Surat Dokter
                                </button>
                            <?php endif; ?>
                        <?php elseif ($r['attendance_notes']): ?>
                            <strong>Catatan:</strong> <?= htmlspecialchars($r['attendance_notes']) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <button type="button" class="btn btn-secondary btn-sm" onclick="openAdjustModal(<?= $r['user_id'] ?>, '<?= htmlspecialchars(addslashes($r['full_name'])) ?>', '<?= htmlspecialchars($r['clock_in'] ?? '') ?>', '<?= htmlspecialchars($r['clock_out'] ?? '') ?>', '<?= htmlspecialchars($r['attendance_status'] ?? 'hadir') ?>', '<?= htmlspecialchars(addslashes($r['attendance_notes'] ?? '')) ?>')" style="width: 100%; border-radius: 10px; font-weight: 700; padding: 8px;">
                    <i class="ti ti-edit"></i> Koreksi / Adjust Presensi
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TAMPILAN KHUSUS DESKTOP (FULL DATA TABLE) -->
    <div class="card-body desktop-only-view">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Operator</th>
                        <th>Divisi & SPV</th>
                        <th>Jadwal Shift</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status Presensi</th>
                        <th>Keterangan / Dokumen</th>
                        <th style="text-align: center;">Aksi (Adjust)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                Tidak ditemukan data karyawan yang sesuai dengan kriteria filter.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($r['position']) ?></div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($r['division_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    SPV: <?= htmlspecialchars($r['supervisor_name'] ?? 'Belum ada') ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($r['shift_name']): ?>
                                    <span class="badge-shift" style="background: <?= htmlspecialchars($r['shift_color']) ?>15; color: <?= htmlspecialchars($r['shift_color']) ?>; border: 1px solid <?= htmlspecialchars($r['shift_color']) ?>40;">
                                        <?= htmlspecialchars($r['shift_name']) ?>
                                    </span>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
                                        (<?= htmlspecialchars($r['start_time']) ?> - <?= htmlspecialchars($r['end_time']) ?>)
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.75rem;">Belum dijadwalkan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['clock_in']): ?>
                                    <span style="font-weight: 700; font-family: monospace; color: var(--text-heading);">
                                        <i class="ti ti-login" style="color: #10b981;"></i> <?= htmlspecialchars($r['clock_in']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['clock_out']): ?>
                                    <span style="font-weight: 700; font-family: monospace; color: var(--text-heading);">
                                        <i class="ti ti-logout" style="color: #ef4444;"></i> <?= htmlspecialchars($r['clock_out']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($r['attendance_status'])) {
                                    echo Helpers::statusBadge($r['attendance_status']);
                                } elseif (!empty($r['leave_type'])) {
                                    echo Helpers::statusBadge($r['leave_type']);
                                } else {
                                    echo Helpers::statusBadge('tidak_hadir');
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($r['leave_type'])): ?>
                                    <div style="font-size: 0.8125rem;">
                                        <?= htmlspecialchars($r['leave_reason']) ?>
                                    </div>
                                    <?php if ($r['doctor_letter']): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="viewDoctorLetter('<?= UPLOAD_URL . htmlspecialchars($r['doctor_letter']) ?>', '<?= htmlspecialchars(addslashes($r['full_name'])) ?>', '<?= $selectedDate ?>')" style="padding: 2px 6px; font-size: 0.7rem; margin-top: 4px; color: #dc2626;">
                                            <i class="ti ti-file-certificate"></i> Lihat Surat Dokter
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($r['attendance_notes']): ?>
                                    <span style="font-size: 0.8125rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($r['attendance_notes']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="openAdjustModal(<?= $r['user_id'] ?>, '<?= htmlspecialchars(addslashes($r['full_name'])) ?>', '<?= htmlspecialchars($r['clock_in'] ?? '') ?>', '<?= htmlspecialchars($r['clock_out'] ?? '') ?>', '<?= htmlspecialchars($r['attendance_status'] ?? 'hadir') ?>', '<?= htmlspecialchars(addslashes($r['attendance_notes'] ?? '')) ?>')" title="Koreksi / Adjust Presensi Karyawan">
                                    <i class="ti ti-edit"></i> Adjust
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL ADJUST / KOREKSI PRESENSI OLEH SUPERADMIN / ADMIN -->
<div class="modal-backdrop" id="adjustAttendanceModal">
    <div class="modal-content" style="max-width: 500px;">
        <form method="POST" action="">
            <input type="hidden" name="action" value="adjust_attendance">
            <input type="hidden" name="user_id" id="adjUserId">
            <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">

            <div class="modal-header">
                <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                    <i class="ti ti-adjustments" style="color: #f59e0b;"></i>
                    Adjust / Koreksi Presensi Pegawai
                </h3>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('adjustAttendanceModal')"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body">
                <div style="background: #fef3c7; border: 1px solid #fde68a; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.8125rem; color: #92400e;">
                    <strong>Pegawai:</strong> <span id="adjUserName" style="font-weight: 700;">-</span><br>
                    <strong>Tanggal:</strong> <?= Helpers::formatTanggalIndo($selectedDate) ?>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Jam Masuk (Clock In)</label>
                        <input type="time" step="1" name="clock_in" id="adjClockIn" class="form-control" placeholder="HH:MM:SS">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jam Pulang (Clock Out)</label>
                        <input type="time" step="1" name="clock_out" id="adjClockOut" class="form-control" placeholder="HH:MM:SS">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Status Presensi</label>
                    <select name="status" id="adjStatus" class="form-control" required>
                        <option value="hadir">Hadir (Tepat Waktu)</option>
                        <option value="terlambat">Terlambat</option>
                        <option value="pulang_cepat">Pulang Cepat</option>
                        <option value="tidak_hadir">Alpha / Tidak Hadir</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Koreksi (Alasan Penyesuaian)</label>
                    <textarea name="notes" id="adjNotes" class="form-control" rows="2" placeholder="Contoh: Pegawai lupa clock-in karena perbaikan mesin darurat"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('adjustAttendanceModal')">Batal</button>
                <button type="submit" class="btn btn-primary" style="background: #f59e0b; border-color: #d97706; color: #fff;">
                    <i class="ti ti-check"></i> Simpan Penyesuaian
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdjustModal(userId, userName, clockIn, clockOut, status, notes) {
    document.getElementById('adjUserId').value = userId;
    document.getElementById('adjUserName').textContent = userName;
    document.getElementById('adjClockIn').value = clockIn;
    document.getElementById('adjClockOut').value = clockOut;
    document.getElementById('adjStatus').value = status || 'hadir';
    document.getElementById('adjNotes').value = notes;
    openModal('adjustAttendanceModal');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
